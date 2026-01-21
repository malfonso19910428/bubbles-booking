<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Wizard_Shell {

    protected $state = array();
    protected $steps = array( 'vehicle', 'package', 'addons', 'address', 'date', 'tech','customer', 'payment' );
    protected $current_step = 'vehicle';

    /** @var BB_Draft_Steps_Service|null */
    protected $draft_service = null;

    public function __construct() {}

    public function handle_request() {

        require_once BB_PLUGIN_DIR . 'includes/core/bb-draft-steps-factory.php';
        $this->draft_service = bb_wizard_draft_steps_service();

        if ( ! $this->draft_service ) {
            return '<div class="bb-error">Draft service not available (bb_wizard_draft_steps_service returned null).</div>';
        }

        $this->state = $this->draft_service->get_state();
        if ( ! is_array( $this->state ) ) $this->state = array();

        $this->state['_dbg_shell_file'] = __FILE__;
        $this->current_step = $this->resolve_current_step();

        // init draft once
        if ( $this->current_step === 'vehicle' && empty( $this->state['_draft_initialized'] ) ) {
            $this->state['_draft_initialized'] = 1;
            $this->draft_service->set_current_step( 'vehicle' );
            $this->sync_state_to_service_and_save( 'draft' );
            $this->state = $this->draft_service->get_state();
        }

        $errors = array();

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

            $nav = isset( $_POST['bb_nav'] ) ? sanitize_key( wp_unslash( $_POST['bb_nav'] ) ) : '';
            $posted_step = isset($_POST['bb_step']) ? sanitize_key( wp_unslash($_POST['bb_step']) ) : '';

            if ( ! in_array( $posted_step, $this->steps, true ) ) {
                $posted_step = $this->current_step;
            }

            // ✅ Bloquear campos address si no estamos en address
            if ( $posted_step !== 'address' ) {
                if ( isset($_POST['bb_validate_address']) ) unset($_POST['bb_validate_address']);
                foreach ( array_keys($_POST) as $k ) {
                    if ( strpos($k, 'bb_address_') === 0 ) unset($_POST[$k]);
                }
                unset($_POST['bb_place_id'], $_POST['bb_address'], $_POST['bb_address_type']);
            }

            // BACK
            if ( isset( $_POST['bb_back'] ) || $nav === 'back' ) {

                $this->state['_bb_nav_back'] = 1;
                $this->current_step = $this->goto_prev_step( $posted_step );

                $this->draft_service->set_current_step( $this->current_step );
                $this->sync_state_to_service_and_save( 'draft' );
                $this->state = $this->draft_service->get_state();

            } else {

                // CONTINUE / ACTION
                $this->state['_bb_nav_back'] = 0;

                $result = $this->handle_post_for_step( $posted_step, $errors );

                // ✅ Si un controller devuelve redirect, lo ejecutamos aquí
                if ( is_array($result) && ! empty($result['redirect']) ) {
                    wp_safe_redirect( $result['redirect'] );
                    exit;
                }

                $continue_pressed = ( isset($_POST['bb_continue']) || $nav === 'continue' );
                if ( $posted_step === 'vehicle' && isset($_POST['bb_vehicle_submit']) ) $continue_pressed = true;

                if ( $continue_pressed && empty($errors) ) {
                    $this->current_step = $this->goto_next_step( $posted_step );
                } else {
                    $this->current_step = $posted_step;
                }

                $this->draft_service->set_current_step( $this->current_step );
                $this->sync_state_to_service_and_save( 'draft' );
                $this->state = $this->draft_service->get_state();
            }
        }

        if ( $this->current_step === 'vehicle' ) $this->state['_bb_nav_back'] = 0;

        $step_controller = $this->make_step_controller( $this->current_step );
        $step_view_data  = array();

        if ( $step_controller && method_exists( $step_controller, 'get_view_data' ) ) {
            $step_view_data = $step_controller->get_view_data( $this->state, $errors );
        }

        // summary
        $summary_vm   = array();
        $grand_total  = 0.0;

        $summary_file = BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-summary-controller.php';
        if ( file_exists( $summary_file ) ) {
            require_once $summary_file;
            if ( class_exists( 'BB_Summary_Controller' ) ) {
                try {
                    $summary_vm = ( new BB_Summary_Controller() )->get_view_data( $this->state );
                    $grand_total = isset( $summary_vm['totals']['grand_total'] )
                        ? (float) $summary_vm['totals']['grand_total']
                        : 0.0;
                } catch ( \Throwable $e ) {
                    $summary_vm  = array();
                    $grand_total = 0.0;
                }
            }
        }

        $view = array(
            'steps'         => $this->steps,
            'current_step'  => $this->current_step,
            'step_template' => 'wizard-step-' . $this->current_step . '.php',
            'step_data'     => $step_view_data,
            'errors'        => $errors,
            'buttons'       => $this->build_buttons_state( $this->current_step ),
            'state'         => $this->state,
            'summary'       => $summary_vm,
            'grand_total'   => $grand_total,
        );

        ob_start();
        $shell_tpl = BB_PLUGIN_DIR . 'templates/wizard/wizard-shell.php';
        if ( file_exists( $shell_tpl ) ) {
            $view_for_template = $view;
            include $shell_tpl;
        } else {
            echo '<p class="bb-error">Wizard shell template not found.</p>';
        }

        return ob_get_clean();
    }

    public function get_state() { return $this->state; }

    protected function sync_state_to_service_and_save( string $status = 'draft' ): void {
        if ( ! $this->draft_service ) return;
        foreach ( $this->state as $k => $v ) {
            $this->draft_service->set_slice( (string) $k, $v );
        }
        $this->draft_service->save( $status );
    }

    protected function resolve_current_step() {
        if ( isset( $_REQUEST['bb_step'] ) ) {
            $step = sanitize_key( wp_unslash( $_REQUEST['bb_step'] ) );
            if ( in_array( $step, $this->steps, true ) ) return $step;
        }
        if ( $this->draft_service ) {
            $step = $this->draft_service->get_current_step();
            if ( in_array( $step, $this->steps, true ) ) return $step;
        }
        return 'vehicle';
    }

    /**
     * ✅ IMPORTANTE: ahora retornamos lo que devuelva el controller (para redirects)
     */
    protected function handle_post_for_step( $step, array &$errors ) {
        $step_controller = $this->make_step_controller( $step );
        if ( ! $step_controller ) return null;

        if ( ! isset($this->state['_dbg_controller_log']) || ! is_array($this->state['_dbg_controller_log']) ) {
            $this->state['_dbg_controller_log'] = array();
        }

        $this->state['_dbg_controller_log'][] = array(
            'ts' => microtime(true),
            'step' => (string) $step,
            'controller_class' => is_object($step_controller) ? get_class($step_controller) : 'none',
            'has_handle_post' => ( is_object($step_controller) && method_exists($step_controller, 'handle_post') ) ? 1 : 0,
        );

        if ( method_exists( $step_controller, 'handle_post' ) ) {
            return $step_controller->handle_post( $_POST, $errors, $this->state );
        }

        return null;
    }

    protected function make_step_controller( $step ) {
        switch ( $step ) {

            case 'vehicle':
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-vehicle-controller.php';
                return new BB_Vehicle_Controller();

            case 'package':
                require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-repo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-service.php';
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-services-controller.php';
                require_once BB_PLUGIN_DIR . 'includes/core/bb-pricing-engine-factory.php';
                $services_service = new BB_Services_Service( new BB_Services_Repo() );
                $pricing_engine = bb_pricing_engine( $services_service );
                return new BB_Services_Controller( $services_service, $pricing_engine );

            case 'addons':
                require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-repo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-service.php';
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-addons-controller.php';
                return new Bubbles_Wizard_Addons_Controller( new BB_Addons_Service( new BB_Addons_Repo() ) );

            case 'address':
                $file = BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-address-controller.php';
                if ( file_exists( $file ) ) require_once $file;
                return class_exists( 'BB_Address_Controller' ) ? new BB_Address_Controller( $this->state ) : null;

            case 'date':
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-availability-controller.php';

                require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-repo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-service.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/availability-engine/bb-availability-engine.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/availability-engine/bb-duration-service.php';

                require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-repo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-service.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-repo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-service.php';

                require_once BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-repo.php';

                $rules_repo    = new BB_Availability_Rules_Repo();
                $rules_service = new BB_Availability_Rules_Service( $rules_repo );

                $tech_repo  = new TechAvailabilityRepo();
                $draft_repo = new BB_Draft_Steps_Repo();

                $services = new BB_Services_Service( new BB_Services_Repo() );
                $addons   = new BB_Addons_Service( new BB_Addons_Repo() );
                $duration = new BB_Duration_Service( $services, $addons );

                $availability_engine = new BB_Availability_Engine(
                    $rules_service,
                    $tech_repo,
                    $draft_repo,
                    $duration
                );

                return new BB_Date_Controller( $this->state, $availability_engine, $this->draft_service );

            case 'tech':
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-tech-controller.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/tech/profile/TechProfileRepo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/tech/profile/TechProfileService.php';

                $profiles = new TechProfileService( new TechProfileRepo() );
                return new BB_Tech_Controller( $this->draft_service, $profiles );

            case 'customer':
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-customer-controller.php';
                return new BB_Customer_Controller( $this->draft_service );

            case 'payment':
                require_once BB_PLUGIN_DIR . 'includes/core/bb-booking-factory.php';
                return bb_payment_controller( $this->draft_service );

            default:
                return null;
        }
    }

    protected function build_buttons_state( $current_step ) {
        $idx = $this->get_step_index( $current_step );
        return array(
            'back_disabled'     => ( $idx <= 0 ),
            'continue_disabled' => ( $idx >= count( $this->steps ) - 1 ),
        );
    }

    protected function get_step_index( $slug ) {
        $idx = array_search( $slug, $this->steps, true );
        return ( false === $idx ) ? 0 : (int) $idx;
    }

    protected function goto_prev_step( $current ) {
        $idx = $this->get_step_index( $current );
        $idx = max( 0, $idx - 1 );
        return $this->steps[ $idx ];
    }

    protected function goto_next_step( $current ) {
        $idx = $this->get_step_index( $current );
        $idx = min( count( $this->steps ) - 1, $idx + 1 );
        return $this->steps[ $idx ];
    }
}
