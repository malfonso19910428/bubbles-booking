<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Settings_Stripe_Repo {

    /**
     * Usamos la misma opción antigua para Stripe:
     * bubbles_booking_options
     * para que bb_create_payment_intent siga funcionando.
     */
    private string $option_name = 'bubbles_booking_options';

    public function get(): array {
        $defaults = array(
            'stripe_mode'        => 'test', // test | live
            'stripe_secret_key'  => '',
            'stripe_public_key'  => '',
            'stripe_currency'    => 'usd',
        );

        $options = get_option( $this->option_name, array() );
        if ( ! is_array( $options ) ) {
            $options = array();
        }

        return wp_parse_args( $options, $defaults );
    }

    public function save( array $settings ): void {

        $options = get_option( $this->option_name, array() );
        if ( ! is_array( $options ) ) {
            $options = array();
        }

        foreach ( array( 'stripe_mode', 'stripe_secret_key', 'stripe_public_key', 'stripe_currency' ) as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $options[ $key ] = $settings[ $key ];
            }
        }

        update_option( $this->option_name, $options );
    }
}
