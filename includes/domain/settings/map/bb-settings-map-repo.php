<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Settings_Map_Repo {

    private string $option_name = 'bubbles_booking_map_settings';

    public function get(): array {
        $defaults = array(
            'google_maps_api_key' => '',
        );

        $options = get_option( $this->option_name, array() );
        if ( ! is_array( $options ) ) {
            $options = array();
        }

        return wp_parse_args( $options, $defaults );
    }

    public function save( array $settings ): void {
        update_option( $this->option_name, $settings );
    }
}
