<?php
// FILE: includes/UI/Wizard/bb-wizard-shell.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BB_Wizard_Shell (REFactor-safe)
 *
 * ✅ V2 draft shell (multi-vehicle)
 * ✅ Single source of truth = V2 draft in DB
 *
 * Helper classes used:
 * - BB_State_Mapper   (draft -> UI state)
 * - BB_Draft_Writer   (apply_step + commit buffer job)
 * - BB_Wizard_Router  (next/prev + guard)
 * - BB_Wizard_Flow    (POST orchestration)
 * - BB_Summary_Controller (VM for summary sidebar)
 */

// ------------------------------------------------------------
// Facade (one entrypoint to factories)
// ------------------------------------------------------------
require_once BB_PLUGIN_DIR . 'includes/core/factories/bb-factory.php';

// ------------------------------------------------------------
// Helper classes (direct requires; not factories)
// ------------------------------------------------------------
require_once BB_PLUGIN_DIR . 'includes/core/draft/bb-draft-normalizer.php';
require_once BB_PLUGIN_DIR . 'includes/core/draft/bb-draft-session-manager.php';
require_once BB_PLUGIN_DIR . 'includes/core/wizard/bb-wizard-flow.php';
require_once BB_PLUGIN_DIR . 'includes/core/wizard/bb-state-mapper.php';
require_once BB_PLUGIN_DIR . 'includes/core/wizard/bb-draft-writer.php';
require_once BB_PLUGIN_DIR . 'includes/core/wizard/bb-wizard-router.php';

// ✅ Summary VM controller (UI layer)
require_once BB_PLUGIN_DIR . 'includes/core/wizard/bb-summary-presenter.php';
require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-summary-controller.php';

class BB_Wizard_Shell {

    protected $state = array();

    protected $steps = array( 'vehicle', 'package', 'addons', 'address', 'date', 'tech', 'customer', 'payment' );
    protected $current_step = 'vehicle';

    /** @var object|null */
    protected $draft_service = null;

    public function __construct() {}

    public function handle_request() {

        // ✅ Draft service via facade
        $this->draft_service = BB_Factory::draft_steps();

        if ( ! $this->draft_service ) {
            return '<div class="bb-error">Draft service not available (BB_Factory::draft_steps returned null).</div>';
        }

        // 1) Load draft from DB
        $draft = $this->draft_service->get_state();
        if ( ! is_array( $draft ) ) $draft = array();

        // 2) Normalize to V2
        $draft = BB_Draft_Normalizer::ensure_v2($draft);

        // 2.6) If current draft is completed -> do NOT resume; start a new draft
        if ( (($draft['booking']['status'] ?? '') === 'completed') ) {
            $this->start_new_draft_and_reset();
            $draft = $this->get_v2_draft_state(); // new clean draft
        }

        // 3) Resolve current step ONLY from DB
        $this->current_step = $this->resolve_current_step();

        // ✅ Guard step (keeps behavior)
        $this->current_step = BB_Wizard_Router::guard_step($draft, $this->steps, $this->current_step);

        // 4) Baseline init: only save if needed
        $dirty = false;

        if ( empty( $draft['_draft_initialized'] ) ) {
            $draft['_draft_initialized'] = 1;
            $dirty = true;
        }

        $resolved_step = $this->current_step ?: 'vehicle';
        if ( (string)($draft['current_step'] ?? '') !== $resolved_step ) {
            $draft['current_step'] = $resolved_step;
            $dirty = true;
        }

        if ( $dirty ) {
            $this->draft_service->set_current_step( $draft['current_step'] );
            $this->draft_service->set_state( $draft );
            $this->draft_service->save( 'draft' );

            // Reload after save
            $draft = $this->draft_service->get_state();
            if ( ! is_array( $draft ) ) $draft = array();
            $draft = BB_Draft_Normalizer::ensure_v2($draft);
        } else {
            // Keep service aligned even if we didn't save
            $this->draft_service->set_current_step( $resolved_step );
        }

        // 5) Build UI state from V2 draft (Mapper = single place)
        $this->rebuild_state_from_draft( $draft );

        $errors = array();

        // =========================
        // POST handling (moved to BB_Wizard_Flow)
        // =========================
        $post_out = BB_Wizard_Flow::handle_post(array(
            'steps'         => $this->steps,
            'current_step'  => $this->current_step,
            'state'         => $this->state,
            'draft_service' => $this->draft_service,

            // callbacks back to shell
            'cb_handle_post_step' => function(string $posted_step, array &$errors) {
                return $this->handle_post_for_step($posted_step, $errors);
            },

            // cb_persist(posted_step, writer_context_step)
            // writer_context_step = step que debe quedar en draft.current_step
            'cb_persist' => function(string $posted_step, string $writer_context_step) {
                $prev = $this->current_step;
                $this->current_step = $writer_context_step;

                $this->persist_ui_state_to_v2_draft($posted_step, 'draft');

                $this->current_step = $prev;
            },

            'cb_get_draft' => function(): array {
                return $this->get_v2_draft_state();
            },
            'cb_rebuild' => function(array $draft_now): void {
                $this->rebuild_state_from_draft($draft_now);
            },
            'cb_start_new_draft' => function(): void {
                $this->start_new_draft_and_reset();
            },
            'cb_reset_keep_address' => function(): void {
                $this->reset_vehicle_pricing_slices_keep_address();
            },
            'cb_mark_add_vehicle' => function(): void {
                $this->mark_add_another_vehicle_in_draft();
            },

            // ✅ NEW: when backing into (vehicle/package/addons) from later steps,
            // uncommit active job (jobs[] -> job_buffer) to avoid "2 packages"
            'cb_uncommit_to_buffer' => function(string $target_step): void {
                $this->uncommit_to_buffer_and_go($target_step);
            },
        ));

        if ( ! empty($post_out['did_post']) ) {

            $this->current_step = (string)($post_out['next_step'] ?? $this->current_step);

            // errors from flow
            $errors = (array)($post_out['errors'] ?? array());

            // track back state
            $this->state['_bb_nav_back'] = ! empty($post_out['nav_back']) ? 1 : 0;

            // ✅ keep service aligned with resolved step (safety)
            if ( $this->draft_service && method_exists($this->draft_service, 'set_current_step') ) {
                $this->draft_service->set_current_step( (string) $this->current_step );
            }

            // ✅ if flow reset draft, rebuild state from fresh draft
            if ( ! empty($post_out['did_reset']) ) {
                $draft = $this->get_v2_draft_state();
                $this->rebuild_state_from_draft($draft);
            }
        }

        // =========================
        // View-model for step
        // =========================
        $step_controller = $this->make_step_controller( $this->current_step );
        $step_view_data  = array();

        if ( $step_controller && method_exists( $step_controller, 'get_view_data' ) ) {
            $step_view_data = $step_controller->get_view_data( $this->state, $errors );
        }

        // =========================
        // Summary VM (via Summary_Controller) - AFTER POST & state rebuild
        // =========================
        $summary_controller = new BB_Summary_Controller();

        // Pass current_step into state for summary consistency
        $state_for_summary = $this->state;
        $state_for_summary['current_step'] = $this->current_step;

        $summary_vm = $summary_controller->get_view_data( $state_for_summary );
        $grand_total = (float) ( $summary_vm['totals']['grand_total'] ?? 0.0 );
        $step_view_data['grand_total'] = $grand_total;

        // =========================
        // View-only glue moved out of template (Parte 2)
        // =========================
        $labels      = $this->get_step_labels();
        $form_action = $this->build_form_action();
        $bb_token    = $this->resolve_bb_token_from_state();

        // Optional: keep template dumb for Start Over too
        $current_url = get_permalink( get_queried_object_id() );

        $start_over_url = add_query_arg(
            array(
                'bb_new'   => '1',
                '_bbnonce' => wp_create_nonce( 'bb_new_draft' ),
            ),
            $current_url
        );

        $start_over_confirm = 'Start over and clear your current booking progress?';

        // Optional: keep template dumb for continue button rule
        $hide_continue = ( $this->current_step === 'payment' );

        $view = array(
            'steps'         => $this->steps,
            'current_step'  => $this->current_step,
            'step_template' => 'wizard-step-' . $this->current_step . '.php',
            'step_data'     => $step_view_data,
            'errors'        => $errors,
            'buttons'       => $this->build_buttons_state( $this->current_step ),
            'state'         => $this->state,

            // ✅ Summary
            'summary_vm'    => $summary_vm,
            'grand_total'   => $grand_total,

            // ✅ Parte 2: moved out of template
            'labels'        => $labels,
            'form_action'   => $form_action,
            'bb_token'      => $bb_token,

            // ✅ Optional (keeps template pure)
            'start_over_url'      => $start_over_url,
            'start_over_confirm'  => $start_over_confirm,
            'hide_continue'       => $hide_continue,
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

    // ------------------------------------------------------------
    // Build UI state from draft using Mapper
    // ------------------------------------------------------------
    protected function rebuild_state_from_draft( array $draft ): void {

        $ui = BB_State_Mapper::to_ui_state($draft, function($d){
            return $this->get_active_job_index($d);
        });

        $this->state = $ui;
        $this->state['_draft_v2'] = $draft;
        $this->state['_dbg_shell_file'] = __FILE__;
    }

    // ✅ NEW: used by Flow when going back into pre-steps
    protected function uncommit_to_buffer_and_go(string $target_step): void {

        if ( ! $this->draft_service ) return;

        $draft = $this->get_v2_draft_state();

        if ( method_exists('BB_Draft_Writer', 'uncommit_active_job_to_buffer') ) {
            $draft = BB_Draft_Writer::uncommit_active_job_to_buffer($draft);
        }

        $draft['current_step'] = $target_step;

        $this->draft_service->set_current_step($target_step);
        $this->draft_service->set_state($draft);
        $this->draft_service->save('draft');

        $this->rebuild_state_from_draft($draft);

        // keep local
        $this->current_step = $target_step;
    }

    protected function start_new_draft_and_reset(): void {
        $out = BB_Draft_Session_Manager::start_new_draft();

        if ( empty($out['draft_service']) ) return;

        $this->draft_service = $out['draft_service'];
        $this->current_step  = 'vehicle';

        $draft = is_array($out['draft']) ? $out['draft'] : array();
        $draft = BB_Draft_Normalizer::ensure_v2($draft);

        $this->rebuild_state_from_draft($draft);
    }

    // =========================================================
    // Draft persistence delegated to BB_Draft_Writer::apply_step()
    // =========================================================
    protected function persist_ui_state_to_v2_draft( string $posted_step, string $status = 'draft' ): void {
        if ( ! $this->draft_service ) return;

        if ( ! method_exists('BB_Draft_Writer', 'apply_step') ) return;

        $draft = $this->get_v2_draft_state();

        $draft = BB_Draft_Writer::apply_step(
            $draft,
            (string) $posted_step,
            (array)  $this->state,
            (string) ($this->current_step ?: 'vehicle')
        );

        $this->draft_service->set_current_step( (string) ($draft['current_step'] ?? 'vehicle') );
        $this->draft_service->set_state( $draft );
        $this->draft_service->save( $status );
    }

    protected function mark_add_another_vehicle_in_draft(): void {
        if ( ! $this->draft_service ) return;

        $draft = $this->get_v2_draft_state();

        // ✅ single source of truth: Draft_Writer mutates the draft
        $draft = BB_Draft_Writer::apply_add_another_vehicle($draft);

        $this->draft_service->set_current_step('vehicle');
        $this->draft_service->set_state($draft);
        $this->draft_service->save('draft');
    }

    protected function get_v2_draft_state(): array {
        $draft = $this->draft_service ? $this->draft_service->get_state() : array();
        if ( ! is_array($draft) ) $draft = array();
        return BB_Draft_Normalizer::ensure_v2($draft);
    }

    protected function get_active_job_index( array $draft ): int {
        $jobs = isset($draft['jobs']) && is_array($draft['jobs']) ? $draft['jobs'] : array();
        $n = count($jobs);
        if ( $n <= 0 ) return 0;

        $i = (int) ( $draft['active_job'] ?? 0 );
        if ( $i < 0 ) $i = 0;
        if ( $i >= $n ) $i = $n - 1;
        return $i;
    }

    // =========================================================
    // Step resolution + controllers (updated to use BB_Factory)
    // =========================================================
    protected function resolve_current_step() {
        if ( $this->draft_service ) {
            $step = $this->draft_service->get_current_step();
            if ( in_array( $step, $this->steps, true ) ) return $step;
        }
        return 'vehicle';
    }

    protected function handle_post_for_step( $step, array &$errors ) {
        $step_controller = $this->make_step_controller( $step );
        if ( ! $step_controller ) return null;

        if ( method_exists( $step_controller, 'handle_post' ) ) {
            return $step_controller->handle_post( $_POST, $errors, $this->state );
        }
        return null;
    }

    protected function make_step_controller( $step ) {

        // ✅ Delegate to facade, which delegates to the real factory
        return BB_Factory::step_controller(
            (string) $step,
            (array)  $this->state,
            $this->draft_service
        );
    }

    protected function build_buttons_state( $current_step ) {
        $idx = BB_Wizard_Router::get_step_index($this->steps, $current_step);
        return array(
            'back_disabled'     => ( $idx <= 0 ),
            'continue_disabled' => ( $idx >= count( $this->steps ) - 1 ),
        );
    }

    /**
     * This method lives in shell because it mutates $this->state.
     * Flow triggers it via cb_reset_keep_address when user adds another vehicle.
     */
    protected function reset_vehicle_pricing_slices_keep_address(): void {
        unset($this->state['vehicle']);
        unset($this->state['vehicle_form']);
        unset($this->state['job_target_id']);

        unset($this->state['package']);
        $this->state['addons'] = array();

        unset($this->state['pricing']);
        unset($this->state['totals']);
        unset($this->state['duration']);

        unset($this->state['_vehicle_type_detected']);
    }

    // ------------------------------------------------------------
    // ViewModel helpers (Parte 2)
    // ------------------------------------------------------------
    protected function get_step_labels(): array {
        return array(
            'vehicle'  => 'Vehicle',
            'package'  => 'Package & price',
            'addons'   => 'Add-ons',
            'address'  => 'Address',
            'date'     => 'Date & time',
            'tech'     => 'Technician',
            'customer' => 'Customer',
            'confirm'  => 'Confirm',
            'payment'  => 'Payment',
        );
    }

    protected function build_form_action(): string {
        return remove_query_arg(
            array( 's', 'bb_vehicle_submit', 'bb_step', 'bb_nav', 'bb_continue', 'bb_back', 'bb_new', '_bbnonce' ),
            get_permalink()
        );
    }

    protected function resolve_bb_token_from_state(): string {
        $state = is_array($this->state) ? $this->state : array();

        if ( ! empty( $state['bb_token'] ) ) return (string) $state['bb_token'];
        if ( ! empty( $state['draft_token'] ) ) return (string) $state['draft_token'];

        $d = ( isset($state['_draft_v2']) && is_array($state['_draft_v2']) ) ? $state['_draft_v2'] : array();
        if ( ! empty( $d['bb_token'] ) ) return (string) $d['bb_token'];
        if ( ! empty( $d['draft_token'] ) ) return (string) $d['draft_token'];

        return '';
    }
}
