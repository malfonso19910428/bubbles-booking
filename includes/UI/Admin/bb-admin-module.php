<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BB_Admin_Module
 *
 * - Menú principal "Bubbles Booking"
 * - Submenús: Dashboard, Catalog, Staff, Bookings, Settings
 * - Conecta shells, controllers y templates
 */
class BB_Admin_Module {

    /** @var BB_Admin_Catalog_Shell|null */
    private ?BB_Admin_Catalog_Shell $catalog_shell = null;

    /** @var BB_Admin_Settings_Shell|null */
    private ?BB_Admin_Settings_Shell $settings_shell = null;

    /** @var BB_Admin_Staff_Controller|null */
    private ?BB_Admin_Staff_Controller $staff_ctrl = null;

    public function __construct() {

        $this->includes();
        $this->init_classes();

        // Menús admin
        add_action( 'admin_menu', array( $this, 'register_menu' ) );

        // ✅ Procesar POST temprano (antes de output) para evitar "headers already sent"
        add_action( 'admin_init', array( $this, 'maybe_handle_staff_post' ), 0 );
    }

    /**
     * Includes admin dependencies
     */
    private function includes(): void {

        // ========= CATALOG =========
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/service-catalogo/bb-admin-target-job-controller.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/service-catalogo/bb-admin-pricing-controller.php';

        require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/tiers/bb-pricing-tiers-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/tiers/bb-pricing-tiers-service.php';

        require_once BB_PLUGIN_DIR . 'includes/domain/admin/job-targets/bb-job-targets-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/job-targets/bb-job-targets-service.php';

        require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-service.php';

        require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-service.php';

        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/service-catalogo/bb-admin-services-controller.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/service-catalogo/bb-admin-addons-controller.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/service-catalogo/bb-admin-catalog-shell.php';

        // ========= SETTINGS =========
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/settings/map/bb-settings-map-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/settings/map/bb-settings-map-service.php';

        require_once BB_PLUGIN_DIR . 'includes/domain/admin/settings/payment/stripe/bb-settings-stripe-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/settings/payment/stripe/bb-settings-stripe-service.php';

        require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-service.php';

        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/settings/bb-admin-settings-shell.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/settings/bb-admin-map-controller.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/settings/bb-admin-payment-controller.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/settings/bb-admin-availability-rules-controller.php';

        // ========= STAFF =========
        require_once BB_PLUGIN_DIR . 'includes/domain/tech/profile/TechProfileRepo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/staff/bb-staff-service.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/staff/bb-admin-staff-controller.php';
    }

    /**
     * Instantiate services, controllers and shells
     */
    private function init_classes(): void {

        // ===== Catalog =====
        $services_repo    = new BB_Services_Repo();
        $services_service = new BB_Services_Service( $services_repo );
        $services_ctrl    = new BB_Admin_Services_Controller( $services_service );

        $job_repo    = new BB_Job_Targets_Repo();
        $job_service = new BB_Job_Targets_Service( $job_repo );
        $job_ctrl    = new BB_Admin_Target_Job_Controller( $job_service );

        $addons_repo    = new BB_Addons_Repo();
        $addons_service = new BB_Addons_Service( $addons_repo );
        $addons_ctrl    = new BB_Admin_Addons_Controller( $addons_service );

        $pricing_repo    = new BB_Pricing_Tiers_Repo();
        $pricing_service = new BB_Pricing_Tiers_Service( $pricing_repo );
        $pricing_ctrl    = new BB_Admin_Pricing_Controller( $pricing_service, $services_service );

        $this->catalog_shell = new BB_Admin_Catalog_Shell(
            $services_ctrl,
            $addons_ctrl,
            $pricing_ctrl,
            $job_ctrl
        );

        // ===== Settings =====
        $map_repo    = new BB_Settings_Map_Repo();
        $map_service = new BB_Settings_Map_Service( $map_repo );

        $stripe_repo    = new BB_Settings_Stripe_Repo();
        $stripe_service = new BB_Settings_Stripe_Service( $stripe_repo );

        $av_repo    = new BB_Availability_Rules_Repo();
        $av_service = new BB_Availability_Rules_Service( $av_repo );
        $av_ctrl    = new BB_Admin_Availability_Rules_Controller( $av_service );

        $this->settings_shell = new BB_Admin_Settings_Shell(
            $map_service,
            $stripe_service,
            $av_ctrl
        );

        // ===== Staff =====
        $profile_repo   = new TechProfileRepo();
        $staff_service  = new BB_Staff_Service( $profile_repo );
        $this->staff_ctrl = new BB_Admin_Staff_Controller( $staff_service );
    }

    /**
     * Procesa POST solo en la pantalla bb-staff y temprano (admin_init).
     * Evita "Cannot modify header information".
     */
    public function maybe_handle_staff_post(): void {

        if ( ! is_admin() ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! $this->staff_ctrl ) return;

        // Solo cuando estamos en la pantalla del plugin
        $page = isset($_GET['page']) ? sanitize_text_field( wp_unslash($_GET['page']) ) : '';
        if ( $page !== 'bb-staff' ) return;

        // Solo si es POST
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) return;

        $this->staff_ctrl->handle_request();
        // handle_request() hace redirect+exit (asegúrate que redirect_with tenga exit)
    }

    /**
     * Admin menu
     */
    public function register_menu() {

        add_menu_page(
            'Bubbles Booking',
            'Bubbles Booking',
            'manage_options',
            'bubbles-booking',
            array( $this, 'render_dashboard' ),
            'dashicons-calendar-alt',
            56
        );

        add_submenu_page(
            'bubbles-booking',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'bubbles-booking',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'bubbles-booking',
            'Catalog',
            'Catalog',
            'manage_options',
            'bb-catalog',
            array( $this, 'render_catalog' )
        );

        add_submenu_page(
            'bubbles-booking',
            'Staff',
            'Staff',
            'manage_options',
            'bb-staff',
            array( $this, 'render_staff' )
        );

        add_submenu_page(
            'bubbles-booking',
            'Bookings',
            'Bookings',
            'manage_options',
            'bb-bookings',
            array( $this, 'render_bookings' )
        );

        add_submenu_page(
            'bubbles-booking',
            'Settings',
            'Settings',
            'manage_options',
            'bubbles-booking-settings',
            array( $this, 'render_settings' )
        );
    }

    // ===================== RENDERS =====================

    public function render_dashboard() {
        $tpl = BB_PLUGIN_DIR . 'templates/Admin/admin-dashboard.php';

        if ( ! file_exists( $tpl ) ) {
            wp_die( 'Admin dashboard template missing:<br><code>' . esc_html( $tpl ) . '</code>' );
        }

        require $tpl;
    }

    public function render_catalog() {

        if ( ! $this->catalog_shell ) {
            wp_die( 'Catalog shell not initialized' );
        }

        $this->catalog_shell->render_screen();
    }

    /**
     * ✅ STAFF (render SOLO)
     * POST ya se procesó en admin_init.
     */
    public function render_staff() {

        if ( ! $this->staff_ctrl ) {
            wp_die( 'Staff controller not initialized' );
        }

        $bb_view = $this->staff_ctrl->get_view_data();

        $tpl = BB_PLUGIN_DIR . 'templates/Admin/staff/admin-staff.php';

        if ( ! file_exists( $tpl ) ) {
            wp_die(
                'Staff template NOT FOUND:<br><code>' . esc_html( $tpl ) . '</code>',
                'Bubbles Booking – Staff Error',
                array( 'response' => 500 )
            );
        }

        require $tpl;
    }

    public function render_bookings() {
        echo '<div class="wrap"><h1>Bookings</h1><p>Coming soon.</p></div>';
    }

    public function render_settings() {

        if ( ! $this->settings_shell ) {
            wp_die( 'Settings shell not initialized' );
        }

        $this->settings_shell->render();
    }
}
