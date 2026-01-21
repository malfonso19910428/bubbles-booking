<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BB_Date_Controller' ) ) {

    class BB_Date_Controller {

        /** @var array */
        protected $state;

        /** @var BB_Availability_Engine */
        protected $availability;

        /** @var BB_Draft_Steps_Service */
        protected $draft;

        public function __construct( array &$state, BB_Availability_Engine $availability, BB_Draft_Steps_Service $draft ) {
            $this->state        = &$state;
            $this->availability = $availability;
            $this->draft        = $draft;
        }

        public function handle_post( array $post, array &$errors, array &$state ) {

            $step = isset( $post['bb_step'] ) ? sanitize_key( wp_unslash( $post['bb_step'] ) ) : '';
            if ( $step !== 'date' ) return null;

            $is_continue = isset( $post['bb_continue'] );

            $date = isset( $post['bb_date'] )
                ? sanitize_text_field( wp_unslash( (string) $post['bb_date'] ) )
                : '';

            $time = isset( $post['bb_time'] )
                ? sanitize_text_field( wp_unslash( (string) $post['bb_time'] ) )
                : '';

            // Basic validations (solo cuando el usuario presiona Continue)
            if ( $is_continue ) {
                if ( $date === '' ) {
                    $errors[] = __( 'Please choose a date.', 'bubbles-booking' );
                } elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                    $errors[] = __( 'Invalid date format.', 'bubbles-booking' );
                }

                if ( $time === '' ) {
                    $errors[] = __( 'Please choose a time slot.', 'bubbles-booking' );
                } elseif ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
                    $errors[] = __( 'Invalid time format.', 'bubbles-booking' );
                }
            }

            /**
             * ✅ Resolver el slot seleccionado (end + tech_ids) desde el engine
             * - start = $time (HH:MM)
             * - end   = calculado (duracion + buffer)
             * - tech_ids = techs disponibles para ese slot (de coverage + weekly availability)
             */
            $slot_end     = '';
            $slot_tech_ids = array();

            if ( $date !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $time !== '' && preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
                $token_for_slots = (string) $this->draft->get_token();
                $slots_for_day   = $this->availability->get_available_slots_for_date( $token_for_slots, $date );

                foreach ( (array) $slots_for_day as $s ) {
                    if ( (string) ( $s['start'] ?? '' ) === $time ) {
                        $slot_end = (string) ( $s['end'] ?? '' );
                        $slot_tech_ids = array_values(
                            array_unique(
                                array_filter(
                                    array_map( 'intval', (array) ( $s['tech_ids'] ?? array() ) )
                                )
                            )
                        );
                        break;
                    }
                }

                // Si presionó Continue y el slot seleccionado ya no existe (stale / race condition)
                if ( $is_continue && empty( $errors ) && empty( $slot_tech_ids ) ) {
                    $errors[] = __( 'That time slot is no longer available. Please pick another.', 'bubbles-booking' );
                }
            }

            // ✅ Guardar estado (incluye slot calculado)
            $state['date'] = array(
                'date' => $date,
                'time' => $time,
                'tz'   => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '',
                'slot' => array(
                    'start'    => $time,
                    'end'      => $slot_end,
                    'tech_ids' => $slot_tech_ids,
                ),
            );

            // ✅ Actualizar draft (manteniendo tu patrón actual)
            $draft_state = $this->draft->get_state();
            if ( ! is_array( $draft_state ) ) $draft_state = array();

            $draft_state['date'] = $state['date'];

            foreach ( $draft_state as $k => $v ) {
                $this->draft->set_slice( (string) $k, $v );
            }

            $this->draft->save( 'draft' );

            // Si hay errores y dio Continue, permanecer en date
            return ( $is_continue && ! empty( $errors ) ) ? 'date' : null;
        }

        public function get_view_data( array $state, array $errors = array() ): array {

            $token = (string) $this->draft->get_token();

            // Fechas disponibles
            $available_dates = $this->availability->get_available_dates( $token );

            // Estado actual del step date
            $d = isset( $state['date'] ) && is_array( $state['date'] ) ? (array) $state['date'] : array();
            $selected_date = (string) ( $d['date'] ?? '' );

            // Slots disponibles: usa la fecha seleccionada si existe, sino el primer día disponible
            $available_slots = array();

            if ( $selected_date !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
                $available_slots = $this->availability->get_available_slots_for_date( $token, $selected_date );
            } elseif ( ! empty( $available_dates ) ) {
                $available_slots = $this->availability->get_available_slots_for_date( $token, (string) $available_dates[0] );
            }

            return array(
                'bb_date'         => (string) ( $d['date'] ?? '' ),
                'bb_time'         => (string) ( $d['time'] ?? '' ),
                'errors'          => $errors,
                'available_dates' => $available_dates,
                'available_slots' => $available_slots,

                // Opcional: para debug/UI (si quieres mostrarlo o inspeccionarlo)
                'selected_slot'   => isset( $d['slot'] ) && is_array( $d['slot'] ) ? $d['slot'] : array(),
            );
        }
    }
}
