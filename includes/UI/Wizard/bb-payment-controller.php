<?php
if ( ! defined('ABSPATH') ) exit;

final class BB_Payment_Controller {

    /** @var BB_Draft_Steps_Service */
    protected $draft;

    public function __construct( $draft_service ) {
        $this->draft = $draft_service;
    }

    public function get_view_data( array $state, array $errors ) : array {
        return array();
    }

    public function handle_post( array $post, array &$errors, array &$state ) {

        $step = isset($post['bb_step']) ? sanitize_key( wp_unslash($post['bb_step']) ) : '';
        if ( $step !== 'payment' ) return null;

        // Solo finalizamos si JS ya confirmó pago y reenvió el form
        if ( empty($post['bb_payment_done']) || $post['bb_payment_done'] !== '1' ) {
            return null;
        }

        if ( empty($post['bb_payment_nonce']) || ! wp_verify_nonce( $post['bb_payment_nonce'], 'bb_payment_step' ) ) {
            $errors[] = 'Invalid request. Please refresh and try again.';
            return null;
        }

        $intent_id = sanitize_text_field( wp_unslash( $post['bb_payment_intent'] ?? '' ) );
        if ( $intent_id === '' ) {
            $errors[] = 'Missing payment intent.';
            return null;
        }

        // Guardar estado de pago
        $state['payment'] = array(
            'completed' => 1,
            'status'    => 'paid',
            'intent_id' => $intent_id,
        );

        // Persistir state
        if ( $this->draft ) {
            foreach ( $state as $k => $v ) {
                $this->draft->set_slice( (string)$k, $v );
            }
            $this->draft->save('draft');
        }

        // Aquí llamas tu checkout final (igual que haces en confirm)
      //  require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/class-bubbles-confirm-checkout.php';

        // ⭐ Necesitas este método en tu checkout (te lo hago abajo)
        return Bubbles_Confirm_Checkout::handle_booking_submit_from_state( $state );
    }
}
