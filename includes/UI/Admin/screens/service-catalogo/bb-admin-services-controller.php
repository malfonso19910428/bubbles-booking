<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Controlador de la pestaña Services
 * Responsable de:
 *  - Manejar el POST de servicios
 *  - Pedir la lista al service de dominio
 *  - Indicar si se creó un servicio
 */
class BB_Admin_Services_Controller {

    /** @var BB_Services_Service */
    protected $service;

    /** @var bool */
    protected $created = false;

    public function __construct( BB_Services_Service $service ) {
        $this->service = $service;
    }

    /**
     * Procesa el formulario de creación de Service
     */
    public function handle_post() {

        if (
            ! isset( $_POST['bb_services_action'] )
            || $_POST['bb_services_action'] !== 'add'
        ) {
            return;
        }

        if ( ! check_admin_referer( 'bb_services_add' ) ) {
            return;
        }

        $created = $this->service->create_from_form( array(
            'name'        => $_POST['service_name']        ?? '',
            'description' => $_POST['service_description'] ?? '',
            'price'       => $_POST['service_price']       ?? 0,
            'duration'    => $_POST['service_duration']    ?? 0,
            'active'      => isset( $_POST['service_active'] ) ? 1 : 0,
        ) );

        $this->created = (bool) $created;
    }

    /**
     * Devuelve la lista de servicios para la vista
     *
     * @return array
     */
    public function get_list() {
        return $this->service->get_all();
    }

    /**
     * Indica si en este request se creó un servicio
     *
     * @return bool
     */
    public function was_created() {
        return $this->created;
    }
}
