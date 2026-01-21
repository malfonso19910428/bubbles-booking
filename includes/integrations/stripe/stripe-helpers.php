<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Stripe Helpers — Bubbles Booking
 * Fuente única de verdad para keys / currency / mode.
 *
 * Lee y escribe sobre: bubbles_booking_options
 */

/**
 * Devuelve el array completo de opciones del plugin.
 */
if ( ! function_exists('bubbles_booking_get_options') ) {
    function bubbles_booking_get_options() : array {

        $opts = get_option( 'bubbles_booking_options', array() );

        if ( ! is_array( $opts ) ) {
            $opts = array();
        }

        return $opts;
    }
}

/**
 * Modo: test | live
 */
if ( ! function_exists('bb_stripe_mode') ) {
    function bb_stripe_mode() : string {

        $opts = bubbles_booking_get_options();
        $m    = isset($opts['stripe_mode']) ? (string)$opts['stripe_mode'] : 'test';

        return ($m === 'live') ? 'live' : 'test';
    }
}

/**
 * Currency ISO-4217 (3 letras). Default: usd
 */
if ( ! function_exists('bb_stripe_currency') ) {
    function bb_stripe_currency() : string {

        $opts = bubbles_booking_get_options();
        $cur  = isset($opts['stripe_currency']) ? (string)$opts['stripe_currency'] : 'usd';

        $cur = strtolower(trim($cur));
        $cur = preg_replace('/[^a-z]/', '', $cur);

        return (strlen($cur) === 3) ? $cur : 'usd';
    }
}

/**
 * Stripe Publishable Key (pk_test / pk_live)
 */
if ( ! function_exists('bb_stripe_public_key') ) {
    function bb_stripe_public_key() : string {

        $opts = bubbles_booking_get_options();
        $key  = isset($opts['stripe_public_key']) ? (string)$opts['stripe_public_key'] : '';

        return trim($key);
    }
}

/**
 * Stripe Secret Key (sk_test / sk_live)
 * ⚠️ SOLO backend
 */
if ( ! function_exists('bb_stripe_secret_key') ) {
    function bb_stripe_secret_key() : string {

        $opts = bubbles_booking_get_options();
        $key  = isset($opts['stripe_secret_key']) ? (string)$opts['stripe_secret_key'] : '';

        return trim($key);
    }
}

/**
 * Verifica si Stripe está configurado
 */
if ( ! function_exists('bb_stripe_is_configured') ) {
    function bb_stripe_is_configured() : bool {

        return (bb_stripe_public_key() !== '' && bb_stripe_secret_key() !== '');
    }
}

/**
 * Verifica que las keys correspondan con el modo (test/live)
 * Evita pk_test con sk_live, etc.
 */
if ( ! function_exists('bb_stripe_keys_match_mode') ) {
    function bb_stripe_keys_match_mode() : bool {

        $mode = bb_stripe_mode();
        $pk   = bb_stripe_public_key();
        $sk   = bb_stripe_secret_key();

        if ( $pk === '' || $sk === '' ) return false;

        if ( $mode === 'test' ) {
            return (strpos($pk, 'pk_test_') === 0) && (strpos($sk, 'sk_test_') === 0);
        }

        // live
        return (strpos($pk, 'pk_live_') === 0) && (strpos($sk, 'sk_live_') === 0);
    }
}
/**
 * Google Places API Key (Address Autocomplete)
 */
if ( ! function_exists('bb_google_places_key') ) {
    function bb_google_places_key() : string {

        $opts = bubbles_booking_get_options();
        $key  = isset($opts['google_places_key']) ? (string)$opts['google_places_key'] : '';

        return trim($key);
    }
}