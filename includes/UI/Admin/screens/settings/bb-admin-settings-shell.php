<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shell de Settings:
 * - Decide el tab activo (map | stripe | availability)
 * - Construye la URL base
 * - Pinta el cascarón y los tabs
 * - Inserta el contenido HTML de cada controlador (map / payment / availability)
 */
class BB_Admin_Settings_Shell {

    /** @var BB_Admin_Settings_Map_Controller */
    protected $map_controller;

    /** @var BB_Admin_Settings_Payment_Controller */
    protected $payment_controller;

    /** @var BB_Admin_Availability_Rules_Controller 
 */
    protected $availability_controller;

    /**
     * @param BB_Settings_Map_Service               $map_service
     * @param BB_Settings_Stripe_Service            $stripe_service
     * @param BB_Admin_Availability_Rules_Controller  $availability_controller
     */
    public function __construct(
        BB_Settings_Map_Service $map_service,
        BB_Settings_Stripe_Service $stripe_service,
        BB_Admin_Availability_Rules_Controller $availability_controller
    ) {
        // Controllers de cada tab, con servicios inyectados
        $this->map_controller          = new BB_Admin_Settings_Map_Controller( $map_service );
        $this->payment_controller      = new BB_Admin_Settings_Payment_Controller( $stripe_service );
        $this->availability_controller = $availability_controller;
    }

    /**
     * Callback del submenú "Settings" (admin.php?page=bubbles-booking-settings)
     */
    public function render(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'bubbles-booking' ) );
        }

        // 1) Tab activo: map | stripe | availability (default: map)
        $active_tab = isset( $_GET['tab'] )
            ? sanitize_key( wp_unslash( $_GET['tab'] ) )
            : 'map';

        if ( ! in_array( $active_tab, array( 'map', 'stripe', 'availability' ), true ) ) {
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

            // Stripe Payments tab
            $this->payment_controller->render_tab();

        } elseif ( $active_tab === 'availability' ) {

            // Availability Rules tab
            $this->render_availability_tab();

        } else {

            // Google Maps tab (default)
            $this->map_controller->render_tab();
        }

        $tab_content = ob_get_clean();

        // 4) Incluir la plantilla shell (cascarón)
        $template = BB_PLUGIN_DIR . 'templates/Admin/admin-settings/settings-shell.php';

        if ( file_exists( $template ) ) {
            // Variables usadas por el template:
            // $active_tab, $settings_page_url, $tab_content
            include $template;
            return;
        }

        echo '<div class="notice notice-error"><p><strong>Bubbles Booking:</strong> Settings shell template not found: <code>templates/Admin/admin-settings/settings-shell.php</code>.</p></div>';
    }

    /**
     * Render Availability Rules tab usando template y variables $rules/$saved/$errors
     */
    private function render_availability_tab(): void {

        if ( ! ( $this->availability_controller instanceof BB_Admin_Availability_Rules_Controller ) ) {
            echo '<div class="notice notice-error"><p>Availability controller not initialized.</p></div>';
            return;
        }

        $data = $this->availability_controller->handle_request();

        // Variables que usa tu template availability.php
        $rules  = $data['rules'] ?? array();
        $saved  = ! empty( $data['saved'] );
        $errors = $data['errors'] ?? array();

        $template = BB_PLUGIN_DIR . 'templates/Admin/admin-settings/availability.php';

        if ( file_exists( $template ) ) {
            require $template;
            return;
        }

        echo '<div class="notice notice-error"><p><strong>Bubbles Booking:</strong> availability.php template not found: <code>templates/Admin/admin-settings/availability.php</code>.</p></div>';
    }
}
