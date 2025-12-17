<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Wizard_Shell {

    protected $state = array();
   protected $steps = array( 'vehicle', 'package', 'addons', 'address', 'date', 'confirm', 'payment' );


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

        if ( $this->current_step === 'vehicle' && empty( $this->state['_draft_initialized'] ) ) {
            $this->state['_draft_initialized'] = 1;
            $this->draft_service->set_current_step( 'vehicle' );
            $this->sync_state_to_service_and_save( 'draft' );

            $this->state = $this->draft_service->get_state();
            if ( ! is_array( $this->state ) ) $this->state = array();
        }

        $errors = array();

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

            $nav = isset( $_POST['bb_nav'] ) ? sanitize_key( wp_unslash( $_POST['bb_nav'] ) ) : '';

            $posted_step = isset($_POST['bb_step']) ? sanitize_key( wp_unslash($_POST['bb_step']) ) : '';
            if ( ! in_array( $posted_step, $this->steps, true ) ) {
                $posted_step = $this->current_step;
            }

            // ✅ si NO estamos en address, no permitas que viajen triggers/address fields
            if ( $posted_step !== 'address' ) {
                if ( isset($_POST['bb_validate_address']) ) unset($_POST['bb_validate_address']);
                foreach ( array_keys($_POST) as $k ) {
                    if ( strpos($k, 'bb_address_') === 0 ) unset($_POST[$k]);
                }
                if ( isset($_POST['bb_place_id']) ) unset($_POST['bb_place_id']);
                if ( isset($_POST['bb_address']) ) unset($_POST['bb_address']);
                if ( isset($_POST['bb_address_type']) ) unset($_POST['bb_address_type']);
            }

            if ( isset( $_POST['bb_back'] ) || $nav === 'back' ) {

                $errors = array();
                $this->state['_bb_nav_back'] = 1;

                $this->current_step = $this->goto_prev_step( $posted_step );

                $this->draft_service->set_current_step( $this->current_step );
                $this->sync_state_to_service_and_save( 'draft' );

                $this->state = $this->draft_service->get_state();
                if ( ! is_array( $this->state ) ) $this->state = array();

            } else {

                $this->state['_bb_nav_back'] = 0;

                $this->state['_dbg_shell_before_controller'] = array(
                    'current_step' => $this->current_step,
                    'post_bb_step' => $posted_step,
                    'has_continue' => isset($_POST['bb_continue']) ? 1 : 0,
                    'has_back'     => isset($_POST['bb_back']) ? 1 : 0,
                    'post_keys'    => array_keys($_POST),
                );

                $this->handle_post_for_step( $posted_step, $errors );

                $this->state['_dbg_shell_after_controller'] = array(
                    'current_step'      => $this->current_step,
                    'handled_step'      => $posted_step,
                    'has_date_in_state' => isset($this->state['date']) ? 1 : 0,
                    'errors_count'      => is_array($errors) ? count($errors) : 0,
                );

                $continue_pressed = ( isset($_POST['bb_continue']) || $nav === 'continue' );

                if ( $posted_step === 'vehicle' && isset($_POST['bb_vehicle_submit']) ) {
                    $continue_pressed = true;
                }

                if ( $continue_pressed && empty($errors) ) {
                    $this->current_step = $this->goto_next_step( $posted_step );
                } else {
                    $this->current_step = $posted_step;
                }

                $this->draft_service->set_current_step( $this->current_step );
                $this->sync_state_to_service_and_save( 'draft' );

                $this->state = $this->draft_service->get_state();
                if ( ! is_array( $this->state ) ) $this->state = array();
            }
        }

        if ( $this->current_step === 'vehicle' ) {
            $this->state['_bb_nav_back'] = 0;
        }

        $step_controller = $this->make_step_controller( $this->current_step );
        $step_view_data  = array();
        if ( $step_controller && method_exists( $step_controller, 'get_view_data' ) ) {
            $step_view_data = $step_controller->get_view_data( $this->state, $errors );
        }

        $summary_vm = array();
        $summary_file = BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-summary-controller.php';
        if ( file_exists( $summary_file ) ) {
            require_once $summary_file;
            if ( class_exists( 'BB_Summary_Controller' ) ) {
                try {
                    $summary_vm = ( new BB_Summary_Controller() )->get_view_data( $this->state );
               // ✅ Extraer total global desde Summary (fuente única de verdad)
$grand_total = 0.0;
if ( isset( $summary_vm['totals']['grand_total'] ) ) {
    $grand_total = (float) $summary_vm['totals']['grand_total'];
}

                } catch ( \Throwable $e ) {
                    $summary_vm = array();
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

    // ✅ FIX: bubbles-wizard.php lo usa
    public function get_state() {
        return $this->state;
    }

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

    protected function handle_post_for_step( $step, array &$errors ) {

        $step_controller = $this->make_step_controller( $step );
        if ( ! $step_controller ) return;

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
            $step_controller->handle_post( $_POST, $errors, $this->state );
        }
    }

    protected function make_step_controller( $step ) {

        switch ( $step ) {

            case 'vehicle':
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-vehicle-controller.php';
                return new BB_Vehicle_Controller();

            case 'package':
                require_once BB_PLUGIN_DIR . 'includes/domain/catalog/services/bb-services-repo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/catalog/services/bb-services-service.php';
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-services-controller.php';
                return new BB_Services_Controller(
                    new BB_Services_Service( new BB_Services_Repo() )
                );

            case 'addons':
                require_once BB_PLUGIN_DIR . 'includes/domain/catalog/addons/bb-addons-repo.php';
                require_once BB_PLUGIN_DIR . 'includes/domain/catalog/addons/bb-addons-service.php';
                require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-addons-controller.php';
                return new Bubbles_Wizard_Addons_Controller(
                    new BB_Addons_Service( new BB_Addons_Repo() )
                );

            case 'address':
                $file = BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-address-controller.php';
                if ( file_exists( $file ) ) require_once $file;
                return class_exists( 'BB_Address_Controller' )
                    ? new BB_Address_Controller( $this->state )
                    : null;

            case 'date':
                $file = BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-availability-controller.php';
                if ( file_exists( $file ) ) require_once $file;
                return class_exists( 'BB_Date_Controller' )
                    ? new BB_Date_Controller( $this->state )
                    : null;

            case 'confirm':
    require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-confirm-controller.php';
    return new BB_Confirm_Controller( $this->draft_service );

case 'payment':
    require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-payment-controller.php';
    return new BB_Payment_Controller( $this->draft_service );
   

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
