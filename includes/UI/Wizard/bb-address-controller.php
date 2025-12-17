<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BB_Address_Controller' ) ) {

    class BB_Address_Controller {

        /** @var array|null Estado global del wizard (referencia) */
        protected $state = null;

        public function __construct( &$state = null ) {
            $this->state = &$state;
        }

        /**
         * Procesa y valida el paso address.
         *
         * @param array $post
         * @param array $errors  (referencia) LISTA de errores (no keyed)
         * @param array $state   (referencia) Estado global del wizard
         *
         * @return string|null   'address' si hay error, null si OK
         */
        public function handle_post( array $post, array &$errors, array &$state ) {

            // Solo nos interesa este controlador en el paso address
            $step = isset( $post['bb_step'] ) ? sanitize_key( wp_unslash( $post['bb_step'] ) ) : '';
            if ( $step !== 'address' ) {
                return null;
            }

            $is_continue = isset( $post['bb_continue'] );
            // Back no valida
            $is_back     = isset( $post['bb_back'] );

            // Helper sanitize
            $sf = function( $key ) use ( $post ) {
                if ( ! isset( $post[ $key ] ) ) return '';
                return sanitize_text_field( wp_unslash( (string) $post[ $key ] ) );
            };

            // 1) Tipo de dirección
            $address_type = $sf( 'bb_address_type' );

            // 2) Campos dirección
            $visible  = $sf( 'bb_address' );
            $extra    = $sf( 'bb_address_extra' );

            $street   = $sf( 'bb_address_street' );
            $city     = $sf( 'bb_address_city' );
            $state_v  = $sf( 'bb_address_state' );
            $zip      = $sf( 'bb_address_zip' );

            $place_id = $sf( 'bb_place_id' );
            $lat      = $sf( 'bb_address_lat' );
            $lng      = $sf( 'bb_address_lng' );

            // 3) Reconstrucción si hidden se perdieron (solo en continue)
            if ( $is_continue && ( $street === '' || $city === '' || $state_v === '' || $zip === '' ) && $visible !== '' ) {

                $parts       = explode( ',', $visible );
                $streetGuess = isset( $parts[0] ) ? trim( $parts[0] ) : '';
                $cityGuess   = isset( $parts[1] ) ? trim( $parts[1] ) : '';
                $stateZip    = isset( $parts[2] ) ? trim( $parts[2] ) : '';

                $sz         = preg_split( '/\s+/', $stateZip );
                $stateGuess = isset( $sz[0] ) ? trim( $sz[0] ) : '';
                $zipGuess   = isset( $sz[1] ) ? trim( $sz[1] ) : '';

                if ( $street === '' )  $street  = $streetGuess;
                if ( $city === '' )    $city    = $cityGuess;
                if ( $state_v === '' ) $state_v = $stateGuess;
                if ( $zip === '' )     $zip     = $zipGuess;
            }

            // 4) Validaciones (solo si Continue)
            if ( $is_continue ) {

                if ( $address_type === '' ) {
                    $errors[] = __( 'Please select if this is your home, work, or other address.', 'bubbles-booking' );
                }

                if ( $visible === '' ) {
                    $errors[] = __( 'Please enter the address where we will work.', 'bubbles-booking' );
                }

                $base_for_number = $street !== '' ? $street : $visible;
                if ( $base_for_number !== '' && ! preg_match( '/\d/', $base_for_number ) ) {
                    $errors[] = __( 'Please enter a complete street address with a house or business number.', 'bubbles-booking' );
                }

                if ( $city === '' || $state_v === '' || $zip === '' ) {
                    $errors[] = __( 'Please enter a full address including city, state, and ZIP code.', 'bubbles-booking' );
                }
            }

            // 5) Formato “formatted” para Job_Builder/summary
            $formatted = $visible;
            if ( $formatted === '' ) {
                // fallback: construir algo usable
                $formatted = trim( $street );
                $tail = trim( $city . ( $state_v !== '' ? ', ' . $state_v : '' ) . ( $zip !== '' ? ' ' . $zip : '' ) );
                if ( $tail !== '' ) {
                    $formatted = trim( $formatted . ', ' . $tail, ', ' );
                }
            }

            // 6) Guardar SIEMPRE lo que haya (para repoblar el form)
            $state['address'] = array(
                'type'      => $address_type,

                // ✅ claves para UI
                'address'   => $visible,
                'extra'     => $extra,
                'street'    => $street,
                'city'      => $city,
                'state'     => $state_v,
                'zip'       => $zip,
                'place_id'  => $place_id,
                'lat'       => $lat,
                'lng'       => $lng,

                // ✅ clave para Job_Builder (lo que él esperaba)
                'formatted' => $formatted,
            );

            // Si es continue y hay errores => quedarse en address
            if ( $is_continue && ! empty( $errors ) ) {
                return 'address';
            }

            // Back o continue sin errores => OK
            return null;
        }

        /**
         * Prepara los valores para la plantilla del paso address.
         */
        public function get_view_data( array $state, array $errors = array() ) {

            $addr = isset( $state['address'] ) ? (array) $state['address'] : array();

            return array(
                'address_type'  => $addr['type']    ?? '',
                'address'       => $addr['address'] ?? '',
                'address_extra' => $addr['extra']   ?? '',

                'city'          => $addr['city']    ?? '',
                'state'         => $addr['state']   ?? '',
                'zip'           => $addr['zip']     ?? '',

                'place_id'      => $addr['place_id'] ?? '',
                'street'        => $addr['street']   ?? '',
                'lat'           => $addr['lat']      ?? '',
                'lng'           => $addr['lng']      ?? '',

                // si quieres mostrarlo en UI/debug
                'formatted'     => $addr['formatted'] ?? '',

                'errors'        => $errors,
            );
        }
    }
}
