<?php
if ( ! defined( 'ABSPATH' ) ) exit;

 {

class BB_Vehicle_Controller {

    public function __construct() {}

    public function handle_post( array $post, array &$errors, array &$state ) : void {

        // 1) Asegurar que estamos en el step correcto
        $step = isset( $post['bb_step'] ) ? sanitize_key( wp_unslash( $post['bb_step'] ) ) : '';

        // Debug visible (opcional)
        $state['_dbg_vehicle'] = array(
            'bb_step'        => $step,
            'has_continue'   => isset( $post['bb_continue'] ) ? 1 : 0,
            'has_submit_btn' => isset( $post['bb_vehicle_submit'] ) ? 1 : 0,
            'keys'           => array_keys( $post ),
        );

        if ( $step !== 'vehicle' ) {
            return;
        }

        // 2) Solo procesar cuando se presiona Continue (shell) o el submit viejo
        $submit = ( isset( $post['bb_continue'] ) || isset( $post['bb_vehicle_submit'] ) );
        if ( ! $submit ) {
            return;
        }

        // 3) Nonce
        if ( isset( $post['bb_vehicle_nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $post['bb_vehicle_nonce'] ) );
            if ( ! wp_verify_nonce( $nonce, 'bb_vehicle_form' ) ) {
                $errors[] = __( 'Security check failed. Please try again.', 'bubbles-booking' );
                return;
            }
        }

        // 4) Leer campos
        $year  = isset( $post['car_year'] )  ? sanitize_text_field( wp_unslash( $post['car_year'] ) )  : '';
        $make  = isset( $post['car_make'] )  ? sanitize_text_field( wp_unslash( $post['car_make'] ) )  : '';
        $model = isset( $post['car_model'] ) ? sanitize_text_field( wp_unslash( $post['car_model'] ) ) : '';
        $color = isset( $post['car_color'] ) ? sanitize_text_field( wp_unslash( $post['car_color'] ) ) : '';

        // 5) Validación mínima
        if ( $year === '' || $make === '' || $model === '' || $color === '' ) {

            // Guardar draft para que el user no pierda lo que escribió
            $state['vehicle_form'] = array(
                'year'  => $year,
                'make'  => $make,
                'model' => $model,
                'color' => $color,
            );

            $errors[] = __( 'Please complete Year, Make, Model and Color.', 'bubbles-booking' );
            return;
        }

        $label = trim( $year . ' ' . $make . ' ' . $model ) . ' (' . $color . ')';

        // 6) Guardar en state (lo que tu Summary y DB necesitan)
        $state['vehicle_form'] = array(
            'year'  => $year,
            'make'  => $make,
            'model' => $model,
            'color' => $color,
            'label' => $label,
        );

        $state['vehicle'] = $state['vehicle_form'];

        // Debug final
        $state['_dbg_vehicle_saved'] = array(
            'vehicle_form' => $state['vehicle_form'],
            'vehicle'      => $state['vehicle'],
        );
    }

    public function get_view_data( array $state, array $errors = array() ) : array {

        $prev = array(
            'year'  => '',
            'make'  => '',
            'model' => '',
            'color' => '',
        );

        if ( isset( $state['vehicle_form'] ) && is_array( $state['vehicle_form'] ) ) {
            $prev = array_merge( $prev, $state['vehicle_form'] );
        } elseif ( isset( $state['vehicle'] ) && is_array( $state['vehicle'] ) ) {
            $prev = array_merge( $prev, $state['vehicle'] );
        }

        return array(
            'prev'        => $prev,
            'errors'      => $errors,
            'message'     => '',
            'bb_vehicles' => '',
        );
    }
}

}
