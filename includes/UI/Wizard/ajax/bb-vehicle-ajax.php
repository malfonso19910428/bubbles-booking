<?php
if ( ! defined('ABSPATH') ) exit;

add_action('wp_ajax_bb_fetch_makes', 'bb_fetch_makes');
add_action('wp_ajax_nopriv_bb_fetch_makes', 'bb_fetch_makes');

add_action('wp_ajax_bb_fetch_models', 'bb_fetch_models');
add_action('wp_ajax_nopriv_bb_fetch_models', 'bb_fetch_models');

function bb_fetch_makes() {
    check_ajax_referer('bb_picker_nonce', 'nonce');

    $cache_key = 'bb_vpic_makes_all_v1';
    $cached = get_transient($cache_key);
    if ( is_array($cached) ) {
        wp_send_json_success($cached);
    }

    $types = array('car', 'truck', 'suv', 'multipurpose%20passenger%20vehicle%20(MPV)');
    $set = array();

    foreach ($types as $t) {
        $url = "https://vpic.nhtsa.dot.gov/api/vehicles/GetMakesForVehicleType/{$t}?format=json";
        $res = wp_remote_get($url, array(
            'timeout'     => 12,
            'redirection' => 2,
            'user-agent'  => 'BubblesBooking/1.0; ' . home_url('/'),
        ));

        if ( is_wp_error($res) ) continue;

        $code = (int) wp_remote_retrieve_response_code($res);
        if ( $code !== 200 ) continue;

        $json = json_decode( wp_remote_retrieve_body($res), true );
        foreach ( $json['Results'] ?? array() as $r ) {
            $name = trim( (string)($r['MakeName'] ?? '') );
            if ($name !== '') $set[$name] = true;
        }
    }

    $makes = array_keys($set);
    sort($makes, SORT_NATURAL | SORT_FLAG_CASE);

    // 7 días: esto cambia MUY raro
    set_transient($cache_key, $makes, 7 * DAY_IN_SECONDS);

    wp_send_json_success($makes);
}

function bb_fetch_models() {
    check_ajax_referer('bb_picker_nonce', 'nonce');

    $make = sanitize_text_field($_GET['make'] ?? '');
    $year = sanitize_text_field($_GET['year'] ?? '');

    // Validación fuerte para evitar requests basura
    if ( $make === '' || $year === '' || ! ctype_digit($year) ) {
        wp_send_json_success(array());
    }

    $y = (int) $year;
    $maxY = (int) date('Y') + 1;
    if ( $y < 1980 || $y > $maxY ) {
        wp_send_json_success(array());
    }

    // Cache normal (éxito)
    $cache_key = 'bb_vpic_models_v1_' . md5(strtolower($make) . '|' . $year);
    $cached = get_transient($cache_key);
    if ( is_array($cached) ) {
        wp_send_json_success($cached);
    }

    // Cache de error (si NHTSA está lento o caído)
    $err_key = $cache_key . '_err';
    $err_cached = get_transient($err_key);
    if ( $err_cached === '1' ) {
        wp_send_json_success(array());
    }

    $url = sprintf(
        'https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeYear/make/%s/modelyear/%s?format=json',
        rawurlencode($make),
        rawurlencode($year)
    );

    $res = wp_remote_get($url, array(
        'timeout'     => 12,
        'redirection' => 2,
        'user-agent'  => 'BubblesBooking/1.0; ' . home_url('/'),
    ));

    if ( is_wp_error($res) ) {
        // Si falla, cachea el fallo 15 min para no spamear
        set_transient($err_key, '1', 15 * MINUTE_IN_SECONDS);
        wp_send_json_success(array());
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    if ( $code !== 200 ) {
        set_transient($err_key, '1', 15 * MINUTE_IN_SECONDS);
        wp_send_json_success(array());
    }

    $json = json_decode( wp_remote_retrieve_body($res), true );
    $models = array();

    foreach ( $json['Results'] ?? array() as $r ) {
        $m = trim( (string)($r['Model_Name'] ?? '') );
        if ($m !== '') $models[$m] = true;
    }

    $models = array_keys($models);
    sort($models, SORT_NATURAL | SORT_FLAG_CASE);

    // 30 días o 7 días también está bien; yo prefiero 30 días por seguridad
    set_transient($cache_key, $models, 30 * DAY_IN_SECONDS);

    wp_send_json_success($models);
}
