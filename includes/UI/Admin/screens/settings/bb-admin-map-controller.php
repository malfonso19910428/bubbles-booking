<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Controlador del tab "Google Maps" en Settings.
 *
 * Usa BB_Settings_Map_Service para:
 *  - procesar el guardado
 *  - recuperar la configuración actual
 *  - pasar los datos a la vista map.php
 */
class BB_Admin_Settings_Map_Controller {

    /** @var BB_Settings_Map_Service */
    protected $map_service;

    public function __construct() {
        // 👇 AQUÍ CREAMOS REPO + SERVICE CORRECTAMENTE
        $repo = new BB_Settings_Map_Repo();
        $this->map_service = new BB_Settings_Map_Service( $repo );
    }

    /**
     * Renderiza SOLO el contenido del tab "map".
     * La shell se encarga del h1 y las tabs.
     */
    public function render_tab() {

        // Procesar guardado (si hay POST)
        $map_saved = $this->map_service->handle_save();

        // Cargar settings actuales
        $settings = $this->map_service->get_settings();

        $current_api_key = isset( $settings['google_maps_api_key'] )
            ? $settings['google_maps_api_key']
            : '';

        // Mensaje de guardado
        if ( $map_saved ) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 esc_html__( 'Google Maps settings saved.', 'bubbles-booking' ) .
                 '</p></div>';
        }

        // Incluir plantilla de este tab
        $template = BB_PLUGIN_DIR . 'templates/Admin/admin-settings/map.php';

        if ( file_exists( $template ) ) {
            // La vista usa: $current_api_key
            include $template;
        } else {
            echo '<div class="notice notice-error"><p><strong>Bubbles Booking:</strong> Map settings template not found: <code>templates/Admin/admin-settings/map.php</code>.</p></div>';
        }
    }
}
