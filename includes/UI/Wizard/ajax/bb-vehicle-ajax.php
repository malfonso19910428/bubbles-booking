<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * AJAX: NHTSA Vehicle Picker (makes/models) + Vehicle Type suggestion + Job Target resolver
 * Nonce: bb_picker_nonce
 */

// Makes
add_action('wp_ajax_bb_fetch_makes', 'bb_fetch_makes');
add_action('wp_ajax_nopriv_bb_fetch_makes', 'bb_fetch_makes');

// Models
add_action('wp_ajax_bb_fetch_models', 'bb_fetch_models');
add_action('wp_ajax_nopriv_bb_fetch_models', 'bb_fetch_models');

// Vehicle type
add_action('wp_ajax_bb_fetch_vehicle_type', 'bb_fetch_vehicle_type');
add_action('wp_ajax_nopriv_bb_fetch_vehicle_type', 'bb_fetch_vehicle_type');

// ✅ Job Target (slug -> id)
add_action('wp_ajax_bb_fetch_job_target', 'bb_fetch_job_target');
add_action('wp_ajax_nopriv_bb_fetch_job_target', 'bb_fetch_job_target');

function bb_fetch_makes() {
    check_ajax_referer('bb_picker_nonce', 'nonce');

    $cache_key = 'bb_vpic_makes_all_v1';
    $cached = get_transient($cache_key);
    if ( is_array($cached) ) {
        wp_send_json_success($cached);
    }

    $types = array(
        'car',
        'truck',
        'suv',
        'multipurpose%20passenger%20vehicle%20(MPV)',
    );

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

    // 7 días (cambia raro)
    set_transient($cache_key, $makes, 7 * DAY_IN_SECONDS);

    wp_send_json_success($makes);
}

function bb_fetch_models() {
    check_ajax_referer('bb_picker_nonce', 'nonce');

    $make = sanitize_text_field($_GET['make'] ?? '');
    $year = sanitize_text_field($_GET['year'] ?? '');

    if ( $make === '' || $year === '' || ! ctype_digit($year) ) {
        wp_send_json_success(array());
    }

    $y = (int) $year;
    $maxY = (int) date('Y') + 1;
    if ( $y < 1980 || $y > $maxY ) {
        wp_send_json_success(array());
    }

    $cache_key = 'bb_vpic_models_v1_' . md5(strtolower($make) . '|' . $year);
    $cached = get_transient($cache_key);
    if ( is_array($cached) ) {
        wp_send_json_success($cached);
    }

    // Cache de error 15 min para no spamear si NHTSA está caído
    $err_key = $cache_key . '_err';
    if ( get_transient($err_key) === '1' ) {
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

    if ( is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200 ) {
        set_transient($err_key, '1', 15 * MINUTE_IN_SECONDS);
        wp_send_json_success(array());
    }

    $json = json_decode( wp_remote_retrieve_body($res), true );
    $models = array();

    foreach ( $json['Results'] ?? array() as $r ) {
        // a veces viene Model_Name
        $m = trim( (string)($r['Model_Name'] ?? $r['ModelName'] ?? '') );
        if ($m !== '') $models[$m] = true;
    }

    $models = array_keys($models);
    sort($models, SORT_NATURAL | SORT_FLAG_CASE);

    set_transient($cache_key, $models, 30 * DAY_IN_SECONDS);

    wp_send_json_success($models);
}

function bb_fetch_vehicle_type() {
    check_ajax_referer('bb_picker_nonce', 'nonce');

    $make  = sanitize_text_field($_GET['make'] ?? '');
    $model = sanitize_text_field($_GET['model'] ?? '');
    $year  = sanitize_text_field($_GET['year'] ?? '');

    if ( $make === '' || $model === '' || $year === '' || ! ctype_digit($year) ) {
        wp_send_json_success(array('suggested' => 'other', 'raw' => array()));
    }

    $cache_key = 'bb_vpic_type_v1_' . md5(strtolower($make).'|'.strtolower($model).'|'.$year);
    $cached = get_transient($cache_key);
    if ( is_array($cached) ) {
        wp_send_json_success($cached);
    }

    // Cache de error 15 min
    $err_key = $cache_key . '_err';
    if ( get_transient($err_key) === '1' ) {
        wp_send_json_success(array('suggested' => 'other', 'raw' => array()));
    }

    $url = sprintf(
        'https://vpic.nhtsa.dot.gov/api/vehicles/GetVehicleTypesForMakeModelYear/make/%s/model/%s/modelyear/%s?format=json',
        rawurlencode($make),
        rawurlencode($model),
        rawurlencode($year)
    );

    $res = wp_remote_get($url, array(
        'timeout'     => 12,
        'redirection' => 2,
        'user-agent'  => 'BubblesBooking/1.0; ' . home_url('/'),
    ));

    if ( is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200 ) {
        set_transient($err_key, '1', 15 * MINUTE_IN_SECONDS);
        wp_send_json_success(array('suggested' => 'other', 'raw' => array()));
    }

    $json = json_decode( wp_remote_retrieve_body($res), true );

    $raw = array();
    foreach ( $json['Results'] ?? array() as $r ) {
        $n = trim((string)($r['VehicleTypeName'] ?? ''));
        if ($n !== '') $raw[] = $n;
    }

    $suggested = 'other';
    $raw_join = strtolower(implode(' | ', $raw));

    // mapping → tu dropdown interno
    if ( strpos($raw_join, 'sport utility') !== false || strpos($raw_join, 'mpv') !== false ) {
        $suggested = 'suv';
    } elseif ( strpos($raw_join, 'truck') !== false ) {
        $suggested = 'pickup';
    } elseif ( strpos($raw_join, 'van') !== false ) {
        $suggested = 'van';
    } elseif ( strpos($raw_join, 'passenger car') !== false ) {
        $suggested = 'sedan';
    }

    $out = array(
        'suggested' => $suggested,
        'raw'       => $raw,
    );

    // éxito 30 días
    set_transient($cache_key, $out, 30 * DAY_IN_SECONDS);

    wp_send_json_success($out);
}

/**
 * ✅ Job target resolver:
 * Given industry + slug (e.g. auto + sedan) returns { item: {id, industry, slug, label, is_active} } or null.
 *
 * It tries to use BB_Job_Targets_Repo if available; otherwise falls back to direct DB query.
 */
function bb_fetch_job_target() {
    check_ajax_referer('bb_picker_nonce', 'nonce');

    $industry = sanitize_key($_GET['industry'] ?? 'auto');
    $slug     = sanitize_key($_GET['slug'] ?? '');

    if ( $slug === '' ) {
        wp_send_json_success(array('item' => null));
    }

    // cache corto para no spamear DB
    $cache_key = 'bb_job_target_v1_' . md5($industry . '|' . $slug);
    $cached = get_transient($cache_key);
    if ( is_array($cached) ) {
        wp_send_json_success(array('item' => $cached));
    }

    // 1) repo (si existe)
    $repo_path = BB_PLUGIN_DIR . 'includes/domain/admin/job-targets/bb-job-targets-repo.php';
    if ( file_exists($repo_path) ) {
        require_once $repo_path;

        if ( class_exists('BB_Job_Targets_Repo') ) {
            $repo = new BB_Job_Targets_Repo();

            $row = null;

            if ( method_exists($repo, 'get_active_by_slug') ) {
                $row = $repo->get_active_by_slug($industry, $slug);
            } elseif ( method_exists($repo, 'get_by_slug') ) {
                $row = $repo->get_by_slug($industry, $slug);
            } elseif ( method_exists($repo, 'find_by_slug') ) {
                $row = $repo->find_by_slug($industry, $slug);
            }

            if ( is_array($row) ) {
                $item = array(
                    'id'       => (int) ($row['id'] ?? 0),
                    'industry'  => (string) ($row['industry'] ?? $industry),
                    'slug'      => (string) ($row['slug'] ?? $slug),
                    'label'     => (string) ($row['label'] ?? $slug),
                    'is_active' => (int) ($row['is_active'] ?? 1),
                );

                if ( $item['id'] > 0 && $item['is_active'] === 1 ) {
                    set_transient($cache_key, $item, 6 * HOUR_IN_SECONDS);
                    wp_send_json_success(array('item' => $item));
                }
            }
        }
    }

    // 2) fallback DB directo
    global $wpdb;
    $table = $wpdb->prefix . 'bb_job_targets';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, industry, slug, label, is_active
             FROM {$table}
             WHERE industry = %s AND slug = %s
             LIMIT 1",
            $industry,
            $slug
        ),
        ARRAY_A
    );

    if ( is_array($row) && (int)($row['id'] ?? 0) > 0 && (int)($row['is_active'] ?? 0) === 1 ) {
        $item = array(
            'id'       => (int) $row['id'],
            'industry'  => (string) $row['industry'],
            'slug'      => (string) $row['slug'],
            'label'     => (string) $row['label'],
            'is_active' => (int) $row['is_active'],
        );

        set_transient($cache_key, $item, 6 * HOUR_IN_SECONDS);
        wp_send_json_success(array('item' => $item));
    }

    wp_send_json_success(array('item' => null));
}
