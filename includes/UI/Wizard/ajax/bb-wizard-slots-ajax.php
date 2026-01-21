<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: Get available slots for a given date
 *
 * action: bb_get_slots
 * GET:
 * - token (string)  draft token
 * - date (YYYY-MM-DD)
 */
add_action( 'wp_ajax_bb_get_slots', 'bb_wizard_get_slots_ajax' );
add_action( 'wp_ajax_nopriv_bb_get_slots', 'bb_wizard_get_slots_ajax' );

function bb_wizard_get_slots_ajax() {

    $token = isset($_GET['token']) ? sanitize_text_field( wp_unslash($_GET['token']) ) : '';
    $date  = isset($_GET['date'])  ? sanitize_text_field( wp_unslash($_GET['date']) )  : '';

    if ( $token === '' ) {
        wp_send_json_error( array( 'reason' => 'missing_token' ), 400 );
    }

    if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        wp_send_json_error( array( 'reason' => 'invalid_date' ), 400 );
    }

    // ===== deps =====
    $deps = array(
        // availability engine
        BB_PLUGIN_DIR . 'includes/domain/availability-engine/bb-availability-engine.php',
        BB_PLUGIN_DIR . 'includes/domain/availability-engine/bb-duration-service.php',

        // rules
        BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-repo.php',
        BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-service.php',

        // tech weekly availability repo
        BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php',

        // draft repo/service
        BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-repo.php',
        BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-service.php',

        // services/addons catalogs (duration)
        BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-repo.php',
        BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-service.php',
        BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-repo.php',
        BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-service.php',
    );

    foreach ( $deps as $f ) {
        if ( file_exists( $f ) ) require_once $f;
    }

    // basic class sanity
    if (
        ! class_exists('BB_Availability_Engine') ||
        ! class_exists('BB_Availability_Rules_Repo') ||
        ! class_exists('BB_Availability_Rules_Service') ||
        ! class_exists('TechAvailabilityRepo') ||
        ! class_exists('BB_Draft_Steps_Repo') ||
        ! class_exists('BB_Duration_Service') ||
        ! class_exists('BB_Services_Repo') ||
        ! class_exists('BB_Services_Service') ||
        ! class_exists('BB_Addons_Repo') ||
        ! class_exists('BB_Addons_Service')
    ) {
        wp_send_json_error( array( 'reason' => 'server_missing_classes' ), 500 );
    }

    // build engine
    $rules_repo    = new BB_Availability_Rules_Repo();
    $rules_service = new BB_Availability_Rules_Service( $rules_repo );

    $tech_repo  = new TechAvailabilityRepo();
    $draft_repo = new BB_Draft_Steps_Repo();

    $services = new BB_Services_Service( new BB_Services_Repo() );
    $addons   = new BB_Addons_Service( new BB_Addons_Repo() );
    $duration = new BB_Duration_Service( $services, $addons );

    $engine = new BB_Availability_Engine(
        $rules_service,
        $tech_repo,
        $draft_repo,
        $duration
    );

    // Get slots
    $slots = $engine->get_available_slots_for_date( $token, $date );

    // Normalize response for JS
    $out = array();
    foreach ( (array) $slots as $s ) {
        $out[] = array(
            'start'    => (string) ($s['start'] ?? ''),
            'end'      => (string) ($s['end'] ?? ''),
            'tech_ids' => isset($s['tech_ids']) && is_array($s['tech_ids']) ? array_values(array_map('intval', $s['tech_ids'])) : array(),
        );
    }

    wp_send_json_success( array(
        'date'  => $date,
        'slots' => $out,
    ) );
}
