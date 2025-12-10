<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TechAvailabilityService {

    /**
     * @var TechAvailabilityRepo
     */
    private $repo;

    /**
     * @param TechAvailabilityRepo $repo
     */
    public function __construct( TechAvailabilityRepo $repo ) {
        $this->repo = $repo;

        // Hook para manejar el formulario en cada carga
        add_action( 'init', array( $this, 'handle_form' ) );
    }

    /**
     * Maneja el POST del formulario de availability
     */
    public function handle_form() {

        // Aseguramos que sea POST
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return;
        }

        // Validar acción específica del formulario
        if (
            ! isset( $_POST['bb_availability_action'] ) ||
            $_POST['bb_availability_action'] !== 'save'
        ) {
            return;
        }

        // Debe estar logueado
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Verificar nonce
        if (
            ! isset( $_POST['bb_availability_nonce'] ) ||
            ! wp_verify_nonce( $_POST['bb_availability_nonce'], 'bb_save_availability' )
        ) {
            wp_die( 'Security check failed', 403 );
        }

        $user_id = get_current_user_id();

        $raw = isset( $_POST['availability'] ) && is_array( $_POST['availability'] )
            ? $_POST['availability']
            : array();

        // Normalizar datos
        $slots = $this->normalize( $raw );

        // Guardar semana completa para este técnico
        $this->repo->replace_week( $user_id, $slots );

        // Redirección de vuelta + flag de "saved=1"
        $redirect = wp_get_referer();

        if ( ! $redirect ) {
            // Fallback: tratar de volver al dashboard con vista availability
            $redirect = add_query_arg(
                'bb_view',
                'availability',
                get_permalink()
            );
        }

        $redirect = add_query_arg( 'saved', '1', $redirect );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Normaliza el array crudo de $_POST['availability']
     *
     * Espera:
     *   availability[1][0][start] = "09:00"
     *   availability[1][0][end]   = "17:00"
     *   ...
     *
     * Devuelve:
     *   [
     *     [ 'weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00' ],
     *     ...
     *   ]
     *
     * @param array $raw
     * @return array
     */
    private function normalize( array $raw ) {

        $out = array();

        foreach ( $raw as $weekday => $daySlots ) {

            if ( ! is_array( $daySlots ) ) {
                continue;
            }

            $weekday_int = (int) $weekday;

            foreach ( $daySlots as $slot ) {

                $start = isset( $slot['start'] ) ? trim( $slot['start'] ) : '';
                $end   = isset( $slot['end'] )   ? trim( $slot['end'] )   : '';

                // Si falta inicio o fin → ignoramos este slot
                if ( $start === '' || $end === '' ) {
                    continue;
                }

                $out[] = array(
                    'weekday'    => $weekday_int,
                    'start_time' => $this->to_time_string( $start ),
                    'end_time'   => $this->to_time_string( $end ),
                );
            }
        }

        return $out;
    }

    /**
     * Convierte "HH:MM" o "H:MM" en "HH:MM:SS"
     *
     * @param string $value
     * @return string
     */
    private function to_time_string( $value ) {

        $value = trim( $value );

        // Si ya viene como HH:MM:SS, lo dejamos
        if ( preg_match( '/^\d{1,2}:\d{2}:\d{2}$/', $value ) ) {
            return $value;
        }

        // Si viene como HH:MM → añadir ":00"
        if ( preg_match( '/^\d{1,2}:\d{2}$/', $value ) ) {
            return $value . ':00';
        }

        // Fallback: partir por ":" y normalizar
        $parts = explode( ':', $value );
        $h = isset( $parts[0] ) ? (int) $parts[0] : 0;
        $m = isset( $parts[1] ) ? (int) $parts[1] : 0;

        $h = max( 0, min( 23, $h ) );
        $m = max( 0, min( 59, $m ) );

        return sprintf( '%02d:%02d:00', $h, $m );
    }

    /**
     * Devuelve la disponibilidad semanal para un técnico,
     * agrupada por día para rellenar el formulario.
     *
     * @param int|null $user_id
     * @return array
     *
     * Estructura:
     *   [
     *     1 => [
     *       [ 'start_time' => '09:00:00', 'end_time' => '17:00:00' ],
     *       ...
     *     ],
     *     2 => [ ... ],
     *     ...
     *   ]
     */
    public function week_for_user( $user_id = null ) {

        if ( ! $user_id ) {
            if ( ! is_user_logged_in() ) {
                return array();
            }
            $user_id = get_current_user_id();
        }

        // Se asume que el repo devuelve filas planas:
        // [ ['weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00'], ... ]
        $rows = $this->repo->get_week( $user_id );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $by_day = array();

        foreach ( $rows as $row ) {

            if ( ! isset( $row['weekday'] ) ) {
                continue;
            }

            $w = (int) $row['weekday'];

            if ( ! isset( $by_day[ $w ] ) ) {
                $by_day[ $w ] = array();
            }

            $by_day[ $w ][] = array(
                'start_time' => isset( $row['start_time'] ) ? $row['start_time'] : '',
                'end_time'   => isset( $row['end_time'] )   ? $row['end_time']   : '',
            );
        }

        return $by_day;
    }
}
