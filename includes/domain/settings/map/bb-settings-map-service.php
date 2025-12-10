<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Settings_Map_Service {

    private BB_Settings_Map_Repo $repo;

    public function __construct( BB_Settings_Map_Repo $repo ) {
        $this->repo = $repo;
    }

    public function handle_save(): bool {

        if ( ! isset( $_POST['bubbles_booking_save_settings'] ) ) {
            return false;
        }

        check_admin_referer( 'bubbles_booking_save_settings' );

        $settings = array(
            'google_maps_api_key' => isset( $_POST['google_maps_api_key'] )
                ? sanitize_text_field( $_POST['google_maps_api_key'] )
                : '',
        );

        $this->repo->save( $settings );
        return true;
    }

    public function get_settings(): array {
        return $this->repo->get();
    }
}
