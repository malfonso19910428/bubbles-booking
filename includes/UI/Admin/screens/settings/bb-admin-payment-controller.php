<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Controlador del tab "Stripe / Payments" en Settings.
 *
 * Usa BB_Settings_Stripe_Service para:
 *  - procesar el guardado
 *  - recuperar la configuración actual
 *  - pasar los datos a la vista payment.php
 */
class BB_Admin_Settings_Payment_Controller {

    /** @var BB_Settings_Stripe_Service */
    protected $stripe_service;

    public function __construct() {
        // 👇 AQUÍ CREAMOS REPO + SERVICE CORRECTAMENTE
        $repo = new BB_Settings_Stripe_Repo();
        $this->stripe_service = new BB_Settings_Stripe_Service( $repo );
    }

    /**
     * Renderiza SOLO el contenido del tab "stripe".
     */
    public function render_tab() {

        // Procesar guardado (si hay POST)
        $stripe_saved    = $this->stripe_service->handle_save();

        // Cargar settings actuales
        $stripe_settings = $this->stripe_service->get_settings();

        $stripe_mode = isset( $stripe_settings['stripe_mode'] )
            ? $stripe_settings['stripe_mode']
            : 'test';

        $stripe_secret_key = isset( $stripe_settings['stripe_secret_key'] )
            ? $stripe_settings['stripe_secret_key']
            : '';

        $stripe_public_key = isset( $stripe_settings['stripe_public_key'] )
            ? $stripe_settings['stripe_public_key']
            : '';

        $stripe_currency = isset( $stripe_settings['stripe_currency'] )
            ? $stripe_settings['stripe_currency']
            : 'usd';

        // Mensaje de guardado
        if ( $stripe_saved ) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 esc_html__( 'Stripe settings saved.', 'bubbles-booking' ) .
                 '</p></div>';
        }

        // Incluir plantilla de este tab
        $template = BB_PLUGIN_DIR . 'templates/Admin/admin-settings/payment.php';

        if ( file_exists( $template ) ) {
            // La vista usa: $stripe_mode, $stripe_secret_key, $stripe_public_key, $stripe_currency
            include $template;
        } else {
            echo '<div class="notice notice-error"><p><strong>Bubbles Booking:</strong> Payment settings template not found: <code>templates/Admin/admin-settings/payment.php</code>.</p></div>';
        }
    }
}
