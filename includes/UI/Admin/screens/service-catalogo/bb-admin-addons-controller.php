<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Controlador de la pestaña Add-ons
 */
class BB_Admin_Addons_Controller {

    /** @var BB_Addons_Service */
    protected $service;

    /** @var bool */
    protected $created = false;

    public function __construct( BB_Addons_Service $service ) {
        $this->service = $service;
    }

    public function handle_post() {

        if (
            ! isset( $_POST['bb_addons_action'] )
            || $_POST['bb_addons_action'] !== 'add'
        ) {
            return;
        }

        if ( ! check_admin_referer( 'bb_addons_add' ) ) {
            return;
        }

        $created = $this->service->create_from_form( array(
            'name'        => $_POST['addon_name']        ?? '',
            'description' => $_POST['addon_description'] ?? '',
            'price'       => $_POST['addon_price']       ?? 0,
            'duration'    => $_POST['addon_duration']    ?? 0,
            'active'      => isset( $_POST['addon_active'] ) ? 1 : 0,
        ) );

        $this->created = (bool) $created;
    }

    public function get_list() {
        return $this->service->get_all();
    }

    public function was_created() {
        return $this->created;
    }
}
