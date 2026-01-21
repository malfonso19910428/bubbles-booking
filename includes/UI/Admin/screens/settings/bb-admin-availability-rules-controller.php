<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Controller para el TAB de Availability Rules dentro de Settings.
 *
 * Requiere:
 * - BB_Availability_Rules_Service
 *
 * Devuelve:
 * - handle_request(): [rules, saved, errors]
 */
class BB_Admin_Availability_Rules_Controller {

    private BB_Availability_Rules_Service $rules_service;

    public function __construct( BB_Availability_Rules_Service $rules_service ) {
        $this->rules_service = $rules_service;
    }

    /**
     * TAB entry point: procesa POST si existe y devuelve data para template.
     */
    public function handle_request(): array {

        $out = array(
            'saved'  => false,
            'errors' => array(),
            'rules'  => $this->get_rules_for_view(),
        );

        if ( isset( $_POST['bb_av_rules_submit'] ) ) {
            $result = $this->handle_post();
            $out['saved']  = $result['saved'];
            $out['errors'] = $result['errors'];
            $out['rules']  = $this->get_rules_for_view(); // reload
        }

        return $out;
    }

    private function handle_post(): array {

        if ( ! current_user_can( 'manage_options' ) ) {
            return array(
                'saved' => false,
                'errors' => array( 'No tienes permisos.' ),
            );
        }

        if (
            ! isset( $_POST['bb_av_rules_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['bb_av_rules_nonce'] ) ),
                'bb_save_av_rules'
            )
        ) {
            return array(
                'saved' => false,
                'errors' => array( 'Nonce inválido. Recarga la página e intenta de nuevo.' ),
            );
        }

        $errors = array();

        $buffer_minutes    = $this->sanitize_int( $_POST['buffer_minutes'] ?? null, 0, 240 );
        $max_jobs_per_day  = $this->sanitize_int( $_POST['max_jobs_per_day'] ?? null, 1, 20 );
        $min_advance_hours = $this->sanitize_int( $_POST['min_advance_hours'] ?? null, 0, 720 );

        $min_start_time = $this->sanitize_time_hhmm( $_POST['min_start_time'] ?? '' );
        $max_start_time = $this->sanitize_time_hhmm( $_POST['max_start_time'] ?? '' );

        if ( $min_start_time === null ) $errors[] = 'Minimum Start Time inválido (HH:MM).';
        if ( $max_start_time === null ) $errors[] = 'Maximum Start Time inválido (HH:MM).';

        if ( empty( $errors ) && $min_start_time && $max_start_time ) {
            if ( $this->time_to_minutes( $min_start_time ) > $this->time_to_minutes( $max_start_time ) ) {
                $errors[] = 'Minimum Start Time no puede ser mayor que Maximum Start Time.';
            }
        }

        if ( ! empty( $errors ) ) {
            return array( 'saved' => false, 'errors' => $errors );
        }

        $ok = true;
        $ok = $ok && $this->rules_service->save( 'buffer_minutes', $buffer_minutes );
        $ok = $ok && $this->rules_service->save( 'min_start_time', $min_start_time );
        $ok = $ok && $this->rules_service->save( 'max_start_time', $max_start_time );
        $ok = $ok && $this->rules_service->save( 'max_jobs_per_day', $max_jobs_per_day );
        $ok = $ok && $this->rules_service->save( 'min_advance_hours', $min_advance_hours );

        if ( ! $ok ) {
            return array(
                'saved' => false,
                'errors' => array( 'Error guardando reglas. Revisa DB/logs.' ),
            );
        }

        return array( 'saved' => true, 'errors' => array() );
    }

    private function get_rules_for_view(): array {
        return array(
            'buffer_minutes'    => (int) $this->rules_service->get( 'buffer_minutes', 30 ),
            'min_start_time'    => (string) $this->rules_service->get( 'min_start_time', '08:00' ),
            'max_start_time'    => (string) $this->rules_service->get( 'max_start_time', '18:00' ),
            'max_jobs_per_day'  => (int) $this->rules_service->get( 'max_jobs_per_day', 3 ),
            'min_advance_hours' => (int) $this->rules_service->get( 'min_advance_hours', 24 ),
        );
    }

    private function sanitize_int( $raw, int $min, int $max ): int {
        $v = (int) $raw;
        if ( $v < $min ) $v = $min;
        if ( $v > $max ) $v = $max;
        return $v;
    }

    private function sanitize_time_hhmm( $raw ): ?string {
        $raw = trim( (string) $raw );
        if ( $raw === '' ) return null;

        if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $raw, $m ) ) return null;

        $hh = (int) $m[1];
        $mm = (int) $m[2];
        if ( $hh < 0 || $hh > 23 ) return null;
        if ( $mm < 0 || $mm > 59 ) return null;

        return sprintf( '%02d:%02d', $hh, $mm );
    }

    private function time_to_minutes( string $hhmm ): int {
        [$hh, $mm] = array_map( 'intval', explode( ':', $hhmm ) );
        return $hh * 60 + $mm;
    }
}
