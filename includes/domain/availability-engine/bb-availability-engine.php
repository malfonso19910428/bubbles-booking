<?php
// FILE: includes/domain/availability-engine/bb-availability-engine.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BB_Availability_Engine
 *
 * FASE 1:
 * - Calcula fechas disponibles + slots por fecha
 * - Usa weekly availability (TechAvailabilityRepo)
 * - Usa duración real (service + addons) vía BB_Duration_Service
 * - Aplica buffer_between_jobs_minutes (regla global)
 * - NO valida contra bookings reales todavía (fase 2)
 */
final class BB_Availability_Engine {

    private BB_Availability_Rules_Service $rules;
    private TechAvailabilityRepo $tech_availability_repo;
    private BB_Draft_Steps_Repo $draft_repo;
    private BB_Duration_Service $duration;

    public function __construct(
        BB_Availability_Rules_Service $rules,
        TechAvailabilityRepo $tech_availability_repo,
        BB_Draft_Steps_Repo $draft_repo,
        BB_Duration_Service $duration
    ) {
        $this->rules = $rules;
        $this->tech_availability_repo = $tech_availability_repo;
        $this->draft_repo = $draft_repo;
        $this->duration = $duration;
    }

    /* =====================================================
     * Draft helper
     * ===================================================== */

    private function draft( string $token ): BB_Draft_Steps_Service {
        return new BB_Draft_Steps_Service( $this->draft_repo, $token );
    }

    /**
     * IDs de techs candidatos (coverage ya guardado en draft).
     */
    private function get_coverage_tech_ids( array $state ): array {

        if ( empty( $state['coverage'] ) || ! is_array( $state['coverage'] ) ) {
            return array();
        }

        $cov = $state['coverage'];

        if ( empty( $cov['ok'] ) ) return array();
        if ( empty( $cov['tech_ids'] ) || ! is_array( $cov['tech_ids'] ) ) return array();

        return array_values( array_filter( array_map( 'intval', $cov['tech_ids'] ) ) );
    }

    /**
     * Duración total (service + addons) + buffer between jobs.
     */
    private function get_job_duration_minutes( array $state ): int {

        $minutes = (int) $this->duration->get_total_duration_min( $state );

        // ✅ Buffer Between Jobs (minutes)
        $buffer = (int) $this->rules->get( 'buffer_between_jobs_minutes', 0 );
        if ( $buffer > 0 ) {
            $minutes += $buffer;
        }

        return max( 0, $minutes );
    }

    /**
     * Lead time cutoff (si aplica).
     */
    private function lead_cutoff_dt(): DateTimeImmutable {
        $tz = wp_timezone();
        $lead = (int) $this->rules->get( 'min_lead_time_minutes', 0 );
        if ( $lead < 0 ) $lead = 0;
        return ( new DateTimeImmutable( 'now', $tz ) )->modify( "+{$lead} minutes" );
    }

    /* =====================================================
     * API PÚBLICA
     * ===================================================== */

    /**
     * Devuelve fechas disponibles (YYYY-MM-DD)
     *
     * @return array<int,string>
     */
    public function get_available_dates( string $token ): array {

        $draft = $this->draft( $token );
        $state = $draft->get_state();

        $tech_ids = $this->get_coverage_tech_ids( $state );
        if ( empty( $tech_ids ) ) return array();

        $job_minutes = $this->get_job_duration_minutes( $state );
        if ( $job_minutes <= 0 ) return array();

        $tz = wp_timezone();

        $horizon = (int) $this->rules->get( 'horizon_days', 14 );
        if ( $horizon <= 0 ) $horizon = 14;

        $closed = $this->rules->get( 'closed_dates', array() );
        if ( ! is_array( $closed ) ) $closed = array();

        $today = new DateTimeImmutable( 'today', $tz );
        $lead_cutoff = $this->lead_cutoff_dt();

        $available = array();

        for ( $i = 0; $i < $horizon; $i++ ) {

            $date = $today->modify( "+{$i} days" );
            $ymd  = $date->format( 'Y-m-d' );

            if ( in_array( $ymd, $closed, true ) ) {
                continue;
            }

            $weekday = (int) $date->format( 'w' ); // 0=Sun

            foreach ( $tech_ids as $tech_id ) {

                $blocks = $this->tech_availability_repo->get_week( (int) $tech_id );
                if ( empty( $blocks ) ) continue;

                foreach ( $blocks as $b ) {

                    if ( (int) ( $b['is_available'] ?? 1 ) !== 1 ) continue;
                    if ( (int) ( $b['weekday'] ?? -1 ) !== $weekday ) continue;

                    $start_time = (string) ( $b['start_time'] ?? '' ); // '09:00:00'
                    $end_time   = (string) ( $b['end_time'] ?? '' );

                    if ( $start_time === '' || $end_time === '' ) continue;

                    $start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $ymd . ' ' . $start_time, $tz );
                    $end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $ymd . ' ' . $end_time,   $tz );
                    if ( ! $start || ! $end ) continue;

                    // lead time (solo si es el mismo día)
                    if ( $ymd === $lead_cutoff->format('Y-m-d') && $start < $lead_cutoff ) {
                        $start = $lead_cutoff;
                    }

                    if ( $end <= $start ) continue;

                    $mins = (int) floor( ( $end->getTimestamp() - $start->getTimestamp() ) / 60 );
                    if ( $mins >= $job_minutes ) {
                        $available[] = $ymd;
                        break 2; // este tech ya hace que el día sea válido
                    }
                }
            }
        }

        $available = array_values( array_unique( $available ) );
        sort( $available );

        return $available;
    }

    /**
     * Devuelve slots disponibles para una fecha (YYYY-MM-DD).
     * En fase 1, devuelve slots con tech_ids por slot.
     *
     * @return array<int,array{start:string,end:string,tech_ids:array<int,int>}>
     */
    public function get_available_slots_for_date( string $token, string $date_ymd ): array {

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_ymd ) ) {
            return array();
        }

        $draft = $this->draft( $token );
        $state = $draft->get_state();

        $tech_ids = $this->get_coverage_tech_ids( $state );
        if ( empty( $tech_ids ) ) return array();

        $job_minutes = $this->get_job_duration_minutes( $state );
        if ( $job_minutes <= 0 ) return array();

        $closed = $this->rules->get( 'closed_dates', array() );
        if ( is_array( $closed ) && in_array( $date_ymd, $closed, true ) ) {
            return array();
        }

        $tz = wp_timezone();
        $dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_ymd, $tz );
        if ( ! $dt ) return array();

        $weekday = (int) $dt->format( 'w' );

        $step = (int) $this->rules->get( 'slot_step_minutes', 30 );
        if ( $step <= 0 ) $step = 30;

        $lead_cutoff = $this->lead_cutoff_dt();

        // slot_map: "HH:MM" => ['start','end','tech_ids'=>[]]
        $slot_map = array();

        foreach ( $tech_ids as $tech_id ) {

            $blocks = $this->tech_availability_repo->get_week( (int) $tech_id );
            if ( empty( $blocks ) ) continue;

            foreach ( $blocks as $b ) {

                if ( (int) ( $b['is_available'] ?? 1 ) !== 1 ) continue;
                if ( (int) ( $b['weekday'] ?? -1 ) !== $weekday ) continue;

                $start_time = (string) ( $b['start_time'] ?? '' );
                $end_time   = (string) ( $b['end_time'] ?? '' );
                if ( $start_time === '' || $end_time === '' ) continue;

                $start_dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_ymd . ' ' . $start_time, $tz );
                $end_dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_ymd . ' ' . $end_time,   $tz );
                if ( ! $start_dt || ! $end_dt ) continue;

                if ( $date_ymd === $lead_cutoff->format('Y-m-d') && $start_dt < $lead_cutoff ) {
                    $start_dt = $lead_cutoff;
                }

                if ( $end_dt <= $start_dt ) continue;

                // redondea start al siguiente step
                $start_dt = $this->ceil_to_step( $start_dt, $step );

                while ( true ) {
                    $slot_end = $start_dt->modify( "+{$job_minutes} minutes" );
                    if ( $slot_end > $end_dt ) break;

                    $k = $start_dt->format( 'H:i' );

                    if ( ! isset( $slot_map[ $k ] ) ) {
                        $slot_map[ $k ] = array(
                            'start'   => $start_dt->format( 'H:i' ),
                            'end'     => $slot_end->format( 'H:i' ),
                            'tech_ids'=> array(),
                        );
                    }

                    $slot_map[ $k ]['tech_ids'][] = (int) $tech_id;

                    $start_dt = $start_dt->modify( "+{$step} minutes" );
                }
            }
        }

        ksort( $slot_map );

        $out = array();
        foreach ( $slot_map as $s ) {
            $s['tech_ids'] = array_values( array_unique( array_map( 'intval', (array) $s['tech_ids'] ) ) );
            $out[] = $s;
        }

        return $out;
    }

    /* =====================================================
     * Utils
     * ===================================================== */

    private function ceil_to_step( DateTimeImmutable $dt, int $step_min ): DateTimeImmutable {
        $ts = $dt->getTimestamp();
        $step = max(1, $step_min) * 60;
        $ceil = (int) ( ceil( $ts / $step ) * $step );
        return ( new DateTimeImmutable( '@' . $ceil ) )->setTimezone( $dt->getTimezone() );
    }
    public function get_available_dates_for_debug(string $token): array {

    $draft = $this->draft($token);
    $state = $draft->get_state();

    $tech_ids = $this->get_coverage_tech_ids($state);
    if (empty($tech_ids)) {
        error_log('DEBUG: No tech coverage IDs found in draft state.');
        return [];
    }

    $job_minutes = $this->get_job_duration_minutes($state);
    if ($job_minutes <= 0) {
        error_log('DEBUG: Job duration is zero or negative: ' . $job_minutes);
        return [];
    }

    $tz = wp_timezone();
    $horizon = (int) $this->rules->get('horizon_days', 14);
    $closed = $this->rules->get('closed_dates', []);
    $today = new DateTimeImmutable('today', $tz);
    $lead_cutoff = $this->lead_cutoff_dt();

    $available = [];

    for ($i = 0; $i < $horizon; $i++) {
        $date = $today->modify("+{$i} days");
        $ymd = $date->format('Y-m-d');

        if (in_array($ymd, $closed, true)) {
            error_log("DEBUG: {$ymd} is in closed dates.");
            continue;
        }

        $weekday = (int) $date->format('w');

        foreach ($tech_ids as $tech_id) {
            $blocks = $this->tech_availability_repo->get_week((int)$tech_id);
            if (empty($blocks)) {
                error_log("DEBUG: Tech {$tech_id} has no weekly blocks.");
                continue;
            }

            foreach ($blocks as $b) {

                if ((int)($b['is_available'] ?? 1) !== 1) continue;
                if ((int)($b['weekday'] ?? -1) !== $weekday) continue;

                $start_time = (string)($b['start_time'] ?? '');
                $end_time = (string)($b['end_time'] ?? '');
                if ($start_time === '' || $end_time === '') continue;

                $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' ' . $start_time, $tz);
                $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' ' . $end_time, $tz);
                if (!$start || !$end) continue;

                if ($ymd === $lead_cutoff->format('Y-m-d') && $start < $lead_cutoff) {
                    $start = $lead_cutoff;
                }

                if ($end <= $start) continue;

                $mins = (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
                if ($mins >= $job_minutes) {
                    error_log("DEBUG: {$ymd} is available for tech {$tech_id} (slot {$start_time}-{$end_time}, duration {$mins} mins).");
                    $available[] = $ymd;
                    break 2; // un tech disponible hace el día válido
                }
            }
        }
    }

    $available = array_values(array_unique($available));
    sort($available);

    error_log('DEBUG: All available dates: ' . implode(', ', $available));

    return $available;
}


}
