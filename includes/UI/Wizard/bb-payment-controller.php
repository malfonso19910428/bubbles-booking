<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('BB_Payment_Controller') ) {

class BB_Payment_Controller {

  /** @var BB_Draft_Steps_Service */
  protected $draft;

  /** @var BB_Booking_Finalize_Service */
  protected $finalize;

  public function __construct( $draft_service, $finalize_service ){
    $this->draft    = $draft_service;
    $this->finalize = $finalize_service;
  }

  public function get_view_data( array $state, array $errors = array() ) : array {
    $missing = array();

    if ( $this->finalize && method_exists($this->finalize, 'validate_draft_state') ) {
      $missing = (array) $this->finalize->validate_draft_state($state);
    }

    return array(
      'can_pay' => empty($missing),
      'missing' => $missing,
    );
  }

  public function handle_post( array $post, array &$errors, array &$state ){

    // ✅ Solo procesa si este POST viene del step payment
    $step = isset($post['bb_step']) ? sanitize_key( wp_unslash($post['bb_step']) ) : '';
    if ( $step !== 'payment' ) return null;

    // Detectar intento de pagar
    $pay = isset($post['bb_pay_submit']) || isset($post['bb_pay']) || (isset($post['bb_nav']) && $post['bb_nav'] === 'pay');
    if ( ! $pay ) return null;

    // (Opcional) nonce
    if ( empty($post['_wpnonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($post['_wpnonce']) ), 'bb_wizard' ) ) {
      $errors[] = 'Security check failed.';
      return null;
    }

    // Validar que el draft tenga lo necesario antes de pagar (pero NO crear booking aquí)
    if ( $this->finalize && method_exists($this->finalize, 'validate_draft_state') ) {
      $missing = (array) $this->finalize->validate_draft_state($state);
      if ( ! empty($missing) ) {
        $errors[] = 'Missing required data before payment: ' . implode(', ', $missing);
        return null;
      }
    }

    // Persistir state al draft antes de que Stripe trabaje
    if ( $this->draft ) {
      foreach ( $state as $k => $v ) {
        $this->draft->set_slice( (string) $k, $v );
      }
      $this->draft->save('draft');
    }

    // ✅ IMPORTANTE:
    // El booking FINAL se crea DESPUÉS del pago (Stripe succeeded) vía AJAX bb_finalize_booking.
    // Aquí solo dejamos que el frontend (bb-stripe.js) continúe.

    return null;
  }
}

}
