<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shell de Settings:
 * - Decide el tab activo (map | stripe)
 * - Construye la URL base
 * - Pinta el cascarón y los tabs
 * - Inserta el contenido HTML de cada controlador (map / payment)
 */
class BB_Admin_Settings_Shell {

    /** @var BB_Admin_Settings_Map_Controller */
    protected $map_controller;

    /** @var BB_Admin_Settings_Payment_Controller */
    protected $payment_controller;

    /**
     * @param BB_Settings_Map_Service    $map_service
     * @param BB_Settings_Stripe_Service $stripe_service
     */
    public function __construct( ) {
        // Controladores de cada tab, con sus servicios inyectados
       $this->map_controller     = new BB_Admin_Settings_Map_Controller();
    $this->payment_controller = new BB_Admin_Settings_Payment_Controller();
    }

    /**
     * Callback del submenú "Settings" (admin.php?page=bubbles-booking-settings)
     */
    public function render() {

        // 1) Tab activo: map | stripe (default: map)
        $active_tab = isset( $_GET['tab'] )
            ? sanitize_key( wp_unslash( $_GET['tab'] ) )
            : 'map';

        if ( ! in_array( $active_tab, array( 'map', 'stripe' ), true ) ) {
            $active_tab = 'map';
        }

        // 2) URL base de la página de Settings
        $settings_page_url = add_query_arg(
            array( 'page' => 'bubbles-booking-settings' ),
            admin_url( 'admin.php' )
        );

        // 3) Ejecutar el controlador de la pestaña activa y capturar su HTML
        ob_start();

        if ( $active_tab === 'stripe' ) {
            $this->payment_controller->render_tab();
        } else {
            $this->map_controller->render_tab();
        }

        $tab_content = ob_get_clean();

        // 4) Incluir la plantilla shell (cascarón)
        $template = BB_PLUGIN_DIR . 'templates/Admin/admin-settings/settings-shell.php';

        if ( file_exists( $template ) ) {
            // Variables que usará el template:
            // $active_tab, $settings_page_url, $tab_content
            include $template;
        } else {
            echo '<div class="notice notice-error"><p><strong>Bubbles Booking:</strong> Settings shell template not found: <code>templates/Admin/admin-settings/settings-shell.php</code>.</p></div>';
        }
    }
}
