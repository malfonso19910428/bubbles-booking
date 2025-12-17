<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BB_Date_Controller' ) ) {

    class BB_Date_Controller {

        /** @var array|null */
        protected $state = null;

        public function __construct( &$state = null ) {
            $this->state = &$state;
        }

        public function handle_post( array $post, array &$errors, array &$state ) {

            $step = isset( $post['bb_step'] ) ? sanitize_key( wp_unslash( $post['bb_step'] ) ) : '';
            if ( $step !== 'date' ) {
                return null;
            }

            $is_continue = isset( $post['bb_continue'] );

            $date = isset($post['bb_date']) ? sanitize_text_field( wp_unslash( (string) $post['bb_date'] ) ) : '';
            $time = isset($post['bb_time']) ? sanitize_text_field( wp_unslash( (string) $post['bb_time'] ) ) : '';

            // Validar solo en continue
            if ( $is_continue ) {
                if ( $date === '' ) {
                    $errors[] = __( 'Please choose a date.', 'bubbles-booking' );
                } elseif ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ) {
                    $errors[] = __( 'Invalid date format.', 'bubbles-booking' );
                }

                if ( $time === '' ) {
                    $errors[] = __( 'Please choose a time slot.', 'bubbles-booking' );
                } elseif ( ! preg_match('/^\d{2}:\d{2}$/', $time) ) {
                    $errors[] = __( 'Invalid time format.', 'bubbles-booking' );
                }
            }

            // Guardar SIEMPRE (para repoblar el form)
            $state['date'] = array(
                'date' => $date,
                'time' => $time,
                'tz'   => function_exists('wp_timezone_string') ? wp_timezone_string() : '',
            );

            if ( $is_continue && ! empty( $errors ) ) {
                return 'date';
            }

            return null;
        }

        public function get_view_data( array $state, array $errors = array() ) {

            $d = isset($state['date']) && is_array($state['date']) ? (array) $state['date'] : array();

            return array(
                'bb_date' => (string) ( $d['date'] ?? '' ),
                'bb_time' => (string) ( $d['time'] ?? '' ),
                'errors'  => $errors,
            );
        }
    }
}
