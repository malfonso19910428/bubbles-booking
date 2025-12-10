<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shell del Catálogo (Services + Add-ons)
 *
 * SOLO orquesta:
 * - qué tab está activa
 * - POST handling
 * - pasar datos a la vista
 */
class BB_Admin_Catalog_Shell {

    protected BB_Admin_Services_Controller $services_controller;
    protected BB_Admin_Addons_Controller $addons_controller;

    public function __construct(
        BB_Admin_Services_Controller $services_controller,
        BB_Admin_Addons_Controller $addons_controller
    ) {
        $this->services_controller = $services_controller;
        $this->addons_controller   = $addons_controller;
    }

    public function render_screen() {

        // 1️⃣ Tab actual
        $current_tab = ( isset( $_GET['bb_tab'] ) && $_GET['bb_tab'] === 'addons' )
            ? 'addons'
            : 'services';

        // 2️⃣ Procesar POST
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $current_tab === 'services'
                ? $this->services_controller->handle_post()
                : $this->addons_controller->handle_post();
        }

        // 3️⃣ Obtener datos
        $bb_services_list   = $this->services_controller->get_list();
        $bb_service_created = $this->services_controller->was_created();

        $bb_addons_list     = $this->addons_controller->get_list();
        $bb_addon_created   = $this->addons_controller->was_created();

        $bb_current_tab = $current_tab;

        // 4️⃣ Cargar vista
        include BB_PLUGIN_DIR . 'templates/Admin/service-catalogo/service-catalog-shell.php';
    }
}
