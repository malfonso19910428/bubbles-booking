<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BB_Admin_Staff_Controller
 *
 * - Handles approve/reject actions (POST) in admin_init (safe redirects)
 * - Provides view data for template
 */
final class BB_Admin_Staff_Controller {

    private BB_Staff_Service $service;

    public function __construct( BB_Staff_Service $service ) {
        $this->service = $service;
    }

    /**
     * ✅ MUST be called by Admin Module
     * We process POST early (before any HTML output) to avoid "headers already sent".
     */
    public function register_hooks(): void {
        add_action( 'admin_init', array( $this, 'handle_request' ), 1 );
    }

    /**
     * Handle POST actions for Staff screen.
     *
     * POST:
     * - bb_staff_action = approve|reject
     * - tech_id
     * - bb_staff_nonce
     */
    public function handle_request(): void {

        if ( ! is_admin() ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Only for our page
        $page = isset($_GET['page']) ? sanitize_text_field( wp_unslash($_GET['page']) ) : '';
        if ( $page !== 'bb-staff' ) return;

        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) return;

        $action = isset($_POST['bb_staff_action']) ? sanitize_text_field( wp_unslash($_POST['bb_staff_action']) ) : '';
        if ( ! in_array( $action, array( 'approve', 'reject' ), true ) ) return;

        // Nonce
        if (
            empty($_POST['bb_staff_nonce']) ||
            ! wp_verify_nonce(
                sanitize_text_field( wp_unslash($_POST['bb_staff_nonce']) ),
                'bb_staff_action'
            )
        ) {
            wp_die( 'Security check failed', 403 );
        }

        $tech_id = isset($_POST['tech_id']) ? (int) $_POST['tech_id'] : 0;
        if ( $tech_id <= 0 ) {
            $this->redirect_with( 'invalid_tech' );
            exit;
        }

        // Ensure target is bb_staff
        $u = get_user_by( 'id', $tech_id );
        if ( ! $u || ! in_array( 'bb_staff', (array) $u->roles, true ) ) {
            $this->redirect_with( 'not_staff' );
            exit;
        }

        $admin_id = (int) get_current_user_id();

        if ( $action === 'approve' ) {
            $this->service->approve( $tech_id, $admin_id );
            $this->redirect_with( 'approved' );
            exit;
        }

        if ( $action === 'reject' ) {
            $reason = isset($_POST['reject_reason']) ? sanitize_text_field( wp_unslash($_POST['reject_reason']) ) : '';
            $this->service->reject( $tech_id, $admin_id, $reason );
            $this->redirect_with( 'rejected' );
            exit;
        }
    }

    /**
     * Build view data for the Staff page template.
     */
    public function get_view_data(): array {

        if ( ! current_user_can( 'manage_options' ) ) {
            return array(
                'errors' => array( 'forbidden' => 'You do not have permission to view this page.' ),
                'users'  => array(),
                'flash'  => array(),
            );
        }

        $rows = $this->service->build_staff_rows();

        $flash = array(
            'msg' => isset($_GET['bb_staff_msg']) ? sanitize_text_field( wp_unslash($_GET['bb_staff_msg']) ) : '',
        );

        return array(
            'errors' => array(),
            'users'  => $rows,
            'flash'  => $flash,
        );
    }

    private function redirect_with( string $msg ): void {
          $url = admin_url( 'admin.php?page=bb-staff' );
    $url = add_query_arg( 'bb_staff_msg', $msg, $url );
    wp_safe_redirect( $url );
    exit;
    }
}
