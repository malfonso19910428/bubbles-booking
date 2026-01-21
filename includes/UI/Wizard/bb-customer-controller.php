<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('BB_Customer_Controller') ) {

class BB_Customer_Controller {

    /** @var BB_Draft_Steps_Service */
    protected $draft;

    public function __construct( $draft_service ) {
        $this->draft = $draft_service;
    }

    public function get_view_data( array $state, array $errors = array() ) : array {
        return array(
            'customer' => $state['customer'] ?? array(),
        );
    }

    public function handle_post( array $post, array &$errors, array &$state ) {

        $step = isset($post['bb_step']) ? sanitize_key( wp_unslash($post['bb_step']) ) : '';
        if ( $step !== 'customer' ) return null;

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

        // ✅ Guardar SIEMPRE en draft para que payment + finalize lo encuentre
        if ( $this->draft ) {
            $this->draft->set_slice( 'customer', $state['customer'] );
            $this->draft->save('draft');
        }

        // Validar solo si el usuario quiere continuar
        $continue = isset($post['bb_continue']) || (isset($post['bb_nav']) && $post['bb_nav'] === 'continue');
        if ( $continue ) {
            if ( $name === '' || $phone === '' || $email === '' ) {
                $errors[] = 'Please fill in name, phone and email.';
            }
        }

        // ✅ NO FINALIZA booking aquí. Eso es después del pago (AJAX finalize).
        return null;
    }
}

}
