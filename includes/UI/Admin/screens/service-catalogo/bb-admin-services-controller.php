<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Admin_Services_Controller {

    /** @var BB_Services_Service */
    protected $service;

    /** @var bool */
    protected $created = false;

    public function __construct( BB_Services_Service $service ) {
        $this->service = $service;
    }

    public function handle_post(): void {

        if (
            ! isset( $_POST['bb_services_action'] ) ||
            sanitize_key( wp_unslash($_POST['bb_services_action']) ) !== 'add'
        ) {
            return;
        }

        if ( ! check_admin_referer( 'bb_services_add' ) ) {
            return;
        }

        $name        = isset($_POST['service_name']) ? sanitize_text_field( wp_unslash($_POST['service_name']) ) : '';
        $description = isset($_POST['service_description']) ? sanitize_textarea_field( wp_unslash($_POST['service_description']) ) : '';
        $price       = isset($_POST['service_price']) ? (float) $_POST['service_price'] : 0.0;
        $duration    = isset($_POST['service_duration']) ? (int) $_POST['service_duration'] : 0;
        $active      = isset($_POST['service_active']) ? 1 : 0;

        if ( $price < 0 ) $price = 0.0;
        if ( $duration < 0 ) $duration = 0;

        $created = $this->service->create_from_form( array(
            'name'        => $name,
            'description' => $description,
            'price'       => $price,     // ✅ tu service ya lo convierte a base_price
            'duration'    => $duration,
            'active'      => $active,
        ) );

        $this->created = (bool) $created;
    }

    public function get_list(): array {
        return $this->service->get_all();
    }

    public function was_created(): bool {
        return $this->created;
    }
}
