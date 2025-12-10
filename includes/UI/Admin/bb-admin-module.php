<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BB_Admin_Module
 *
 * Responsable de:
 * - Registrar el menú principal "Bubbles Booking" en wp-admin
 * - Registrar submenús (Dashboard, Catalog, Bookings, Settings)
 * - Instanciar el catálogo (services + addons) con su Shell
 * - Instanciar la Shell de Settings (tabs: Map, Payments, etc.)
 */
class BB_Admin_Module {

    /** @var BB_Admin_Catalog_Shell|null */
    private ?BB_Admin_Catalog_Shell $catalog_shell = null;

    /** @var BB_Admin_Settings_Shell|null */
    private ?BB_Admin_Settings_Shell $settings_shell = null;

    public function __construct() {

        // Incluir clases necesarias del admin
        $this->includes();

        // Instanciar clases internas (repos, services, controllers, shells)
        $this->init_classes();

        // Registrar menús del admin
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    /**
     * Incluye clases usadas por el módulo admin
     */
    private function includes(): void {

        // Dominio: Services catalog
        require_once BB_PLUGIN_DIR . 'includes/domain/catalog/services/bb-services-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/catalog/services/bb-services-service.php';

        // Dominio: Add-ons catalog
        require_once BB_PLUGIN_DIR . 'includes/domain/catalog/addons/bb-addons-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/catalog/addons/bb-addons-service.php';

        // UI catálogo: controladores + shell
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/service-catalogo/bb-admin-services-controller.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/service-catalogo/bb-admin-addons-controller.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/service-catalogo/bb-admin-catalog-shell.php';

        // ⚙️ Dominio Settings: MAP (ajusta rutas si son distintas)
        require_once BB_PLUGIN_DIR . 'includes/domain/settings/map/bb-settings-map-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/settings/map/bb-settings-map-service.php';

        // ⚙️ Dominio Settings: STRIPE (ajusta rutas si son distintas)
        require_once BB_PLUGIN_DIR . 'includes/domain/settings/payment/stripe/bb-settings-stripe-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/settings/payment/stripe/bb-settings-stripe-service.php';

        // UI Settings: shell + controladores de tabs
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/settings/bb-admin-settings-shell.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/settings/bb-admin-map-controller.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/Admin/screens/settings/bb-admin-payment-controller.php';
    }

    /**
     * Instancia clases de dominio + controllers + shells
     */
    private function init_classes(): void {

        // === Catálogo: dominio + controllers ===
        $services_repo    = new BB_Services_Repo();
        $services_service = new BB_Services_Service( $services_repo );
        $services_ctrl    = new BB_Admin_Services_Controller( $services_service );

        $addons_repo    = new BB_Addons_Repo();
        $addons_service = new BB_Addons_Service( $addons_repo );
        $addons_ctrl    = new BB_Admin_Addons_Controller( $addons_service );

        // Shell (tabs + layout) – solo lógica de catálogo
        $this->catalog_shell = new BB_Admin_Catalog_Shell( $services_ctrl, $addons_ctrl );

        // === Settings: dominio Map + Stripe ===
        // Ajusta los nombres si tus clases se llaman distinto
        $map_repo     = new BB_Settings_Map_Repo();
        $map_service  = new BB_Settings_Map_Service( $map_repo );

        $stripe_repo    = new BB_Settings_Stripe_Repo();
        $stripe_service = new BB_Settings_Stripe_Service( $stripe_repo );

        // Shell de Settings: controla tabs internos (map, payments, etc.)
        $this->settings_shell = new BB_Admin_Settings_Shell( $map_service, $stripe_service );
    }

    /**
     * Registrar menú principal y submenús
     */
    public function register_menu() {

        // Menú principal
        add_menu_page(
            'Bubbles Booking',              // Page title
            'Bubbles Booking',              // Menu title
            'manage_options',               // Capability
            'bubbles-booking',              // Menu slug
            array( $this, 'render_dashboard' ),
            'dashicons-calendar-alt',
            56
        );

        // Submenú: Dashboard
        add_submenu_page(
            'bubbles-booking',
            'Bubbles Booking',
            'Dashboard',
            'manage_options',
            'bubbles-booking',
            array( $this, 'render_dashboard' )
        );

        // Submenú: Catalog (Services + Add-ons)
        add_submenu_page(
            'bubbles-booking',              // parent_slug
            'Services & Add-ons',           // page_title
            'Catalog',                      // menu_title
            'manage_options',               // capability
            'bb-catalog',                   // menu_slug (slug lógico)
            array( $this, 'render_catalog' )// callback
        );

        // Submenú: Bookings (placeholder)
        add_submenu_page(
            'bubbles-booking',
            'Bookings',
            'Bookings',
            'manage_options',
            'bubbles-booking-bookings',
            array( $this, 'render_bookings' )
        );

        // Submenú: Settings
        add_submenu_page(
            'bubbles-booking',
            'Settings',
            'Settings',
            'manage_options',
            'bubbles-booking-settings',
            array( $this, 'render_settings' )
        );
    }

    /**
     * Render principal del dashboard
     */
    public function render_dashboard() {
        $template = BB_PLUGIN_DIR . 'templates/Admin/admin-dashboard.php';

        if ( file_exists( $template ) ) {
            require $template;
        } else {
            echo '<div class="wrap"><h1>Bubbles Booking</h1><p>Admin dashboard coming soon.</p></div>';
        }
    }

    /**
     * Render del submenú Catalog: delega en el shell
     */
    public function render_catalog() {

        if ( $this->catalog_shell instanceof BB_Admin_Catalog_Shell ) {
            $this->catalog_shell->render_screen();
        } else {
            echo '<div class="wrap"><h1>Catalog</h1><p>Catalog shell not initialized.</p></div>';
        }
    }

    public function render_bookings() {
        echo '<div class="wrap"><h1>Bookings</h1><p>Bookings management coming soon.</p></div>';
    }

    /**
     * Render del submenú Settings: delega en la Settings_Shell
     */
    public function render_settings() {

        if ( $this->settings_shell instanceof BB_Admin_Settings_Shell ) {
            $this->settings_shell->render();
        } else {
            echo '<div class="wrap"><h1>Settings</h1><p>Settings shell not initialized.</p></div>';
        }
    }
}
