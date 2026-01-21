<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Settings_Stripe_Service {

    /** @var BB_Settings_Stripe_Repo */
    protected $repo;

    public function __construct( BB_Settings_Stripe_Repo $repo ) {
        $this->repo = $repo;
    }

    /**
     * Procesa el POST del tab Stripe.
     *
     * @return bool true si guardó, false si no había POST
     */
    public function handle_save(): bool {

        if ( ! isset( $_POST['bb_stripe_save_settings'] ) ) {
            return false;
        }

        check_admin_referer( 'bubbles_booking_save_stripe_settings' );

        // Modo: test | live
        $mode = isset( $_POST['stripe_mode'] )
            ? sanitize_text_field( $_POST['stripe_mode'] )
            : 'test';

        if ( $mode !== 'live' ) {
            $mode = 'test';
        }

        $secret = isset( $_POST['stripe_secret_key'] )
            ? sanitize_text_field( $_POST['stripe_secret_key'] )
            : '';

        $public = isset( $_POST['stripe_public_key'] )
            ? sanitize_text_field( $_POST['stripe_public_key'] )
            : '';

        $currency = isset( $_POST['stripe_currency'] )
            ? strtolower( sanitize_text_field( $_POST['stripe_currency'] ) )
            : 'usd';

        if ( $currency === '' ) {
            $currency = 'usd';
        }

        $settings = array(
            'stripe_mode'       => $mode,
            'stripe_secret_key' => $secret,
            'stripe_public_key' => $public,
            'stripe_currency'   => $currency,
        );

        $this->repo->save( $settings );

        return true;
    }

    /**
     * Devuelve los settings actuales de Stripe
     */
    public function get_settings(): array {
        return $this->repo->get();
    }
}
