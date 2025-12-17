<?php
if ( ! defined('ABSPATH') ) exit;

add_action( 'wp_ajax_bb_create_payment_intent', 'bb_create_payment_intent' );
add_action( 'wp_ajax_nopriv_bb_create_payment_intent', 'bb_create_payment_intent' );

function bb_create_payment_intent() {
  error_log('Stripe PK loaded: ' . ( function_exists('bb_stripe_public_key') && bb_stripe_public_key() ? 'YES' : 'NO' ));
    error_log('Stripe SK loaded: ' . ( function_exists('bb_stripe_secret_key') && bb_stripe_secret_key() ? 'YES' : 'NO' ));
    
    // ✅ Nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'bb_create_pi' ) ) {
        wp_send_json_error(array('message' => 'Invalid request.'), 403);
    }

    // ✅ Options helper loaded?
    if ( ! function_exists('bubbles_booking_get_options') ) {
        wp_send_json_error(array('message' => 'Options helper not loaded.'), 500);
    }

    $options = bubbles_booking_get_options();
    $secret  = trim((string)($options['stripe_secret_key'] ?? ''));

    if ( $secret === '' ) {
        wp_send_json_error(array('message' => 'Stripe secret key is missing.'), 500);
    }

    // ✅ Amount
    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
    if ( $amount <= 0 ) {
        wp_send_json_error(array('message' => 'Invalid amount.'), 400);
    }
    $amount_cents = (int) round($amount * 100);

    // ✅ Currency (3 letras)
    $currency_raw = isset($_POST['currency']) ? (string) wp_unslash($_POST['currency']) : 'usd';
    $currency = strtolower( preg_replace('/[^a-z]/', '', $currency_raw) );
    if ( strlen($currency) !== 3 ) {
        wp_send_json_error(array('message' => 'Invalid currency.'), 400);
    }

   $body = array(
    'amount'   => $amount_cents,
    'currency' => $currency,
    'automatic_payment_methods[enabled]' => 'true',
);

    $response = wp_remote_post(
        'https://api.stripe.com/v1/payment_intents',
        array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            // WP convertirá arrays a form-encoded con [] bien
            'body' => $body,
        )
    );

    if ( is_wp_error($response) ) {
        wp_send_json_error(array('message' => $response->get_error_message()), 500);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $raw  = (string) wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    // Si Stripe devolvió JSON con error, úsalo
    if ( is_array($data) && isset($data['error']['message']) ) {
        wp_send_json_error(array(
            'message' => (string) $data['error']['message'],
            'type'    => (string) ($data['error']['type'] ?? ''),
            'code'    => (string) ($data['error']['code'] ?? ''),
        ), $code ?: 400);
    }

    if ( $code < 200 || $code >= 300 || ! is_array($data) ) {
        wp_send_json_error(array('message' => 'Stripe API error.'), $code ?: 500);
    }

    if ( empty($data['client_secret']) ) {
        wp_send_json_error(array('message' => 'Stripe did not return a client_secret.'), 500);
    }

    wp_send_json_success(array(
        'client_secret'     => $data['client_secret'],
        'payment_intent_id' => (string)($data['id'] ?? ''),
    ));
}
