<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shell del Catálogo (Services + Add-ons + Pricing + Job Targets)
 *
 * SOLO orquesta:
 * - qué tab está activa
 * - POST handling
 * - pasar datos a la vista
 */
class BB_Admin_Catalog_Shell {

    protected BB_Admin_Services_Controller $services_controller;
    protected BB_Admin_Addons_Controller $addons_controller;
    protected BB_Admin_Pricing_Controller $pricing_controller;

    // ✅ NUEVO: Job Targets
    protected BB_Admin_Target_Job_Controller $job_targets_controller;

    public function __construct(
        BB_Admin_Services_Controller $services_controller,
        BB_Admin_Addons_Controller $addons_controller,
        BB_Admin_Pricing_Controller $pricing_controller,
        BB_Admin_Target_Job_Controller $job_targets_controller
    ) {
        $this->services_controller     = $services_controller;
        $this->addons_controller       = $addons_controller;
        $this->pricing_controller      = $pricing_controller;
        $this->job_targets_controller  = $job_targets_controller;
    }

    public function render_screen() {

        // 1️⃣ Tab actual (services | addons | pricing | job_targets)
        $tab = isset( $_GET['bb_tab'] )
            ? sanitize_key( wp_unslash( $_GET['bb_tab'] ) )
            : 'services';

        // ✅ Agregamos job_targets
        $allowed_tabs = array( 'services', 'addons', 'pricing', 'job_targets' );
        $current_tab  = in_array( $tab, $allowed_tabs, true ) ? $tab : 'services';
        

        // 2️⃣ Procesar POST (solo si aplica)
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper( sanitize_text_field( wp_unslash($_SERVER['REQUEST_METHOD']) ) )
            : 'GET';

        // Variables de salida para la vista
        $bb_services_list   = array();
        $bb_service_created = false;

        $bb_addons_list     = array();
        $bb_addon_created   = false;

        $bb_pricing_view    = array();

        // ✅ NUEVO: view para job targets
        $bb_job_targets_view = array();

        // 2.1) POST routing
        if ( $method === 'POST' ) {

            if ( $current_tab === 'services' ) {
                $this->services_controller->handle_post();

            } elseif ( $current_tab === 'addons' ) {
                $this->addons_controller->handle_post();

            } elseif ( $current_tab === 'pricing' ) {
                // ✅ Pricing: controlador separado
                $this->pricing_controller->handle_post();

            } else {
                // ✅ Job Targets: todo se procesa en handle_request (POST + GET)
                // No hacemos nada aquí para evitar doble nonce/POST.
            }
        }

        // 3️⃣ Obtener datos (solo para tabs que lo usan)
        if ( $current_tab === 'services' ) {

            $bb_services_list   = (array) $this->services_controller->get_list();
            $bb_service_created = (bool) $this->services_controller->was_created();

        } elseif ( $current_tab === 'addons' ) {

            $bb_addons_list     = (array) $this->addons_controller->get_list();
            $bb_addon_created   = (bool) $this->addons_controller->was_created();

        } elseif ( $current_tab === 'pricing' ) {

            // pricing
            $bb_pricing_view = (array) $this->pricing_controller->get_view_data();

        } else {

            // ✅ job_targets: el controller maneja GET/POST y retorna view data
            $bb_job_targets_view = (array) $this->job_targets_controller->handle_request();
        }

        $bb_current_tab = $current_tab;

        // 4️⃣ Cargar vista (la vista renderiza tabs)
        include BB_PLUGIN_DIR . 'templates/Admin/service-catalogo/service-catalog-shell.php';
    }
}
