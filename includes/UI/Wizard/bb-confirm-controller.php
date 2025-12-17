<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('BB_Confirm_Controller') ) {

class BB_Confirm_Controller {

    /** @var BB_Draft_Steps_Service */
    protected $draft;

    public function __construct( $draft_service ) {
        $this->draft = $draft_service;
    }

    public function get_view_data( array $state, array $errors ) : array {
        return array(
            'customer' => $state['customer'] ?? array(),
        );
    }

    /**
     * Si devuelve string => el Shell debe renderizarlo y salir (thankyou/error).
     * Si devuelve null => el Shell sigue normal.
     */
    public function handle_post( array $post, array &$errors, array &$state ) {

        $step = isset($post['bb_step']) ? sanitize_key( wp_unslash($post['bb_step']) ) : '';
        if ( $step !== 'confirm' ) return null;

        // Guardar customer en state (siempre)
        $name  = sanitize_text_field( wp_unslash( $post['bb_name']  ?? '' ) );
        $phone = sanitize_text_field( wp_unslash( $post['bb_phone'] ?? '' ) );
        $email = sanitize_email( wp_unslash( $post['bb_email'] ?? '' ) );
        $notes = wp_kses_post( wp_unslash( $post['bb_notes'] ?? '' ) );

        $state['customer'] = array(
            'name'  => $name,
            'phone' => $phone,
            'email' => $email,
            'notes' => $notes,
        );

        // Si NO es submit final, solo guardamos y listo
        if ( empty($post['bb_confirm_booking']) ) {
            return null;
        }

        // Nonce (recomendado)
        if ( empty($post['bb_confirm_nonce']) || ! wp_verify_nonce( $post['bb_confirm_nonce'], 'bb_confirm_booking' ) ) {
            $errors[] = 'Invalid request. Please refresh and try again.';
            return null;
        }

        // Validación mínima
        if ( $name === '' || $phone === '' || $email === '' ) {
            $errors[] = 'Please fill in name, phone and email.';
            return null;
        }

        // Persistir state antes de checkout (así no dependes de POST)
        if ( $this->draft ) {
            foreach ( $state as $k => $v ) {
                $this->draft->set_slice( (string) $k, $v );
            }
            $this->draft->save('draft');
        }

        // Checkout FINAL desde STATE
        require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/class-bubbles-confirm-checkout.php';
        return Bubbles_Confirm_Checkout::handle_booking_submit_from_state( $state );
    }
}

}
