<?php
if ( ! defined('ABSPATH') ) exit;

add_action('wp_ajax_bb_finalize_booking', 'bb_ajax_finalize_booking');
add_action('wp_ajax_nopriv_bb_finalize_booking', 'bb_ajax_finalize_booking');

function bb_ajax_finalize_booking(){

  // Ensure JSON errors (avoid HTML wp_die surprises)
  if ( ! defined('DOING_AJAX') || ! DOING_AJAX ) {
    wp_send_json_error(array('message' => 'Invalid context.'), 400);
  }

  $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
  if ( ! wp_verify_nonce( $nonce, 'bb_finalize_booking_nonce' ) ) {
    wp_send_json_error(array('message' => 'Invalid request (nonce).'), 403);
  }

  $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field( wp_unslash($_POST['payment_intent_id']) ) : '';
  if ( $payment_intent_id === '' ) {
    wp_send_json_error(array('message' => 'Missing payment_intent_id.'), 400);
  }

  // --- Load Draft service ---
  $draft_factory = BB_PLUGIN_DIR . 'includes/core/bb-draft-steps-factory.php';
  if ( ! file_exists($draft_factory) ) {
    wp_send_json_error(array('message' => 'Draft factory missing.'), 500);
  }
  require_once $draft_factory;

  if ( ! function_exists('bb_wizard_draft_steps_service') ) {
    wp_send_json_error(array('message' => 'Draft service function missing.'), 500);
  }

  $draft = bb_wizard_draft_steps_service();
  if ( ! $draft ) {
    wp_send_json_error(array('message' => 'Draft service not available.'), 500);
  }

  $state = $draft->get_state();
  if ( ! is_array($state) ) $state = array();

  $cust = $state['customer'] ?? array();
  if ( empty($cust['name']) || empty($cust['phone']) || empty($cust['email']) ) {
    wp_send_json_error(array('message' => 'Customer details missing.'), 422);
  }

  // --- Load Finalize service + Repo (direct, no factory dependency) ---
  $repo_file = BB_PLUGIN_DIR . 'includes/domain/wizard/bb-booking-repo.php';
  $svc_file  = BB_PLUGIN_DIR . 'includes/domain/wizard/bb-booking-service.php';

  if ( ! file_exists($repo_file) || ! file_exists($svc_file) ) {
    wp_send_json_error(array('message' => 'Booking domain files missing.'), 500);
  }

  require_once $repo_file;
  require_once $svc_file;

  if ( ! class_exists('BB_Booking_Repository') || ! class_exists('BB_Booking_Finalize_Service') ) {
    wp_send_json_error(array('message' => 'Booking classes not available.'), 500);
  }

  $finalize = new BB_Booking_Finalize_Service( new BB_Booking_Repository() );

  if ( ! method_exists($finalize, 'finalize_from_draft_state') ) {
    wp_send_json_error(array('message' => 'Finalize method missing.'), 500);
  }

  $result = $finalize->finalize_from_draft_state( $state );

  if ( empty($result['ok']) ) {
    wp_send_json_error(array('message' => $result['message'] ?? 'Finalize failed.'), 500);
  }

  $booking_id = (int) ($result['booking_id'] ?? 0);

  // Redirect back to the same page (wizard page) with booking id
  $redirect = add_query_arg(
    array('bb_booking' => $booking_id),
    wp_get_referer() ? wp_get_referer() : home_url('/')
  );

  // Mark draft completed (optional)
  if ( method_exists($draft, 'save') ) {
    $draft->save('completed');
  }

  wp_send_json_success(array(
    'booking_id' => $booking_id,
    'redirect'   => $redirect,
  ));
}
