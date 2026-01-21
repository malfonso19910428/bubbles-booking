<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: Address coverage check
 *
 * action: bb_wizard_address_coverage
 * POST:
 * - nonce (string)
 * - token (string)
 * - lat (float)
 * - lng (float)
 * - place_id (optional)
 * - formatted (optional)
 *
 * Saves into Draft slice "address":
 *  - location
 *  - coverage (yes/no + reason + tech_ids/distances)
 *
 * Also saves into Draft slice "coverage" (root) so Availability Engine can read state['coverage'].
 */
add_action( 'wp_ajax_bb_wizard_address_coverage', 'bb_wizard_address_coverage_ajax' );
add_action( 'wp_ajax_nopriv_bb_wizard_address_coverage', 'bb_wizard_address_coverage_ajax' );

function bb_wizard_address_coverage_ajax() {

    $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bb_address_coverage_nonce' ) ) {
        wp_send_json_error( array( 'reason' => 'bad_nonce' ), 403 );
    }

    $token = isset($_POST['token']) ? sanitize_text_field( wp_unslash($_POST['token']) ) : '';
    if ( $token === '' ) {
        wp_send_json_error( array( 'reason' => 'missing_token' ), 400 );
    }

    if ( ! isset($_POST['lat'], $_POST['lng']) ) {
        wp_send_json_error( array( 'reason' => 'invalid_location' ), 400 );
    }

    $lat = (float) wp_unslash($_POST['lat']);
    $lng = (float) wp_unslash($_POST['lng']);

    $place_id  = isset($_POST['place_id']) ? sanitize_text_field( wp_unslash($_POST['place_id']) ) : '';
    $formatted = isset($_POST['formatted']) ? sanitize_text_field( wp_unslash($_POST['formatted']) ) : '';

    $location = array(
        'lat'       => $lat,
        'lng'       => $lng,
        'place_id'  => $place_id,
        'formatted' => $formatted,
    );

    /**
     * Load deps
     */
    $deps = array(
        BB_PLUGIN_DIR . 'includes/domain/tech/profile/TechProfileRepo.php',
        BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php',
        BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-repo.php',
        BB_PLUGIN_DIR . 'includes/domain/availability-engine/bb-coverage-service.php',
    );

    foreach ( $deps as $f ) {
        if ( file_exists( $f ) ) {
            require_once $f;
        }
    }

    if (
        ! class_exists('TechProfileRepo') ||
        ! class_exists('TechAvailabilityRepo') ||
        ! class_exists('BB_Draft_Steps_Repo') ||
        ! class_exists('BB_Coverage_Service')
    ) {
        wp_send_json_error( array( 'reason' => 'server_missing_classes' ), 500 );
    }

    $draft_repo        = new BB_Draft_Steps_Repo();
    $profile_repo      = new TechProfileRepo();
    $availability_repo = new TechAvailabilityRepo();

    // ✅ Updated: pass availability repo so coverage filters techs with no schedule
    $coverage = new BB_Coverage_Service( $draft_repo, $profile_repo, $availability_repo );

    /**
     * 1) Coverage check
     */
    $result = $coverage->check_coverage( $token, $location );

    /**
     * 2) Normalize to draft shape
     */
    $coverage_state = array(
        'ok'        => false,
        'yes'       => false,
        'reason'    => '',
        'tech_ids'  => array(),
        'distances' => array(),
        'checked_at'=> current_time('mysql'),
        'location'  => $location,
    );

    if ( is_array( $result ) ) {

        // yes/ok
        if ( array_key_exists( 'has_tech', $result ) ) {
            $coverage_state['yes'] = (bool) $result['has_tech'];
        } elseif ( array_key_exists( 'tech_count', $result ) ) {
            $coverage_state['yes'] = ( (int) $result['tech_count'] >= 1 );
        } elseif ( array_key_exists( 'yes', $result ) ) {
            $coverage_state['yes'] = (bool) $result['yes'];
        } elseif ( array_key_exists( 'ok', $result ) ) {
            $coverage_state['yes'] = (bool) $result['ok'];
        }

        $coverage_state['ok'] = $coverage_state['yes'];

        // reason
        if ( isset( $result['reason'] ) ) {
            $coverage_state['reason'] = (string) $result['reason'];
        }

        // keep tech_ids/distances (important for availability engine + debug)
        if ( isset($result['tech_ids']) && is_array($result['tech_ids']) ) {
            $coverage_state['tech_ids'] = array_values(array_filter(array_map('intval', $result['tech_ids'])));
        }

        if ( isset($result['distances']) && is_array($result['distances']) ) {
            $coverage_state['distances'] = $result['distances'];
        }
    }

    /**
     * 3) Save into Draft slice "address"
     */
    $draft_repo->upsert(
        $token,
        'address',
        array(
            'location' => $location,
            'coverage' => $coverage_state,
        ),
        'draft'
    );

    /**
     * 3b) Save ALSO into Draft slice "coverage" (root)
     * Availability Engine reads state['coverage'].
     */
    $draft_repo->upsert(
        $token,
        'coverage',
        $coverage_state,
        'draft'
    );

    /**
     * 4) Return result to UI
     */
    wp_send_json_success( array(
        'coverage' => $coverage_state,
        'raw'      => $result,
    ) );
}
