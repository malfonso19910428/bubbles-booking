<?php
if ( ! defined('ABSPATH') ) exit;

// Domain
require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-booking-repo.php';
require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-booking-service.php';

// UI Controllers
// ✅ confirm = customer details (no finalize here)
require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-customer-controller.php';

// ✅ payment = finalize booking
require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-payment-controller.php';

function bb_booking_repository(){
  static $repo = null;
  if ( $repo ) return $repo;
  $repo = new BB_Booking_Repository();
  return $repo;
}

function bb_booking_finalize_service(){
  static $svc = null;
  if ( $svc ) return $svc;
  $svc = new BB_Booking_Finalize_Service( bb_booking_repository() );
  return $svc;
}

/**
 * ✅ Confirm step: only collects/saves customer data into draft/state.
 * NO booking finalization here.
 */
function bb_confirm_controller( $draft_service ){
  return new bb_customer_controller( $draft_service );
}

/**
 * ✅ Payment step: creates the final booking from draft/state
 * (and later you can add Stripe PaymentIntent here).
 */
function bb_payment_controller( $draft_service ){
  return new BB_Payment_Controller( $draft_service, bb_booking_finalize_service() );
}
