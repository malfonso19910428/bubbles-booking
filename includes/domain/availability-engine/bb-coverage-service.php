
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class BB_Coverage_Service {

    private BB_Draft_Steps_Repo   $draft_repo;
    private TechProfileRepo       $profile_repo;
    private TechAvailabilityRepo  $availability_repo;

    public function __construct(
        BB_Draft_Steps_Repo $draft_repo,
        TechProfileRepo $profile_repo,
        TechAvailabilityRepo $availability_repo
    ) {
        $this->draft_repo        = $draft_repo;
        $this->profile_repo      = $profile_repo;
        $this->availability_repo = $availability_repo;
    }

    /**
     * @param string $draft_token
     * @param array  $location  ['lat'=>float,'lng'=>float,'place_id'?,'formatted'?]
     */
    public function check_coverage( string $draft_token, array $location ): array {

        if ( ! isset($location['lat'], $location['lng']) ) {
            $this->save_no_coverage( $draft_token, 'invalid_location' );
            return $this->no_coverage_result( 'invalid_location' );
        }

        $client_lat = (float) $location['lat'];
        $client_lng = (float) $location['lng'];

        if ( $client_lat === 0.0 && $client_lng === 0.0 ) {
            $this->save_no_coverage( $draft_token, 'invalid_location' );
            return $this->no_coverage_result( 'invalid_location' );
        }

        // ✅ IDs int
        $tech_ids = $this->get_all_staff_user_ids();

        $candidates = array();

        foreach ( $tech_ids as $tech_id ) {

            $tech_id = (int) $tech_id;
            if ( $tech_id <= 0 ) continue;

            $profile = $this->profile_repo->get_profile( $tech_id );

            if ( (string)($profile['status'] ?? '') !== 'approved' ) {
                continue;
            }

            $radius = (float) ($profile['radius'] ?? 0);
            if ( $radius <= 0 ) {
                continue;
            }

            $geo = ( isset($profile['geo']) && is_array($profile['geo']) ) ? $profile['geo'] : array();

            $tech_lat = isset($geo['lat']) ? (float) $geo['lat'] : 0.0;
            $tech_lng = isset($geo['lng']) ? (float) $geo['lng'] : 0.0;

            if ( $tech_lat === 0.0 && $tech_lng === 0.0 ) {
                continue;
            }

            $distance = $this->distance_miles(
                $tech_lat,
                $tech_lng,
                $client_lat,
                $client_lng
            );

            if ( $distance <= $radius ) {

                // ✅ NUEVO: solo cuenta si tiene schedule usable
                if ( ! $this->tech_has_any_schedule( $tech_id ) ) {
                    // Está dentro del radio, pero no tiene horario → no puede dar servicio
                    continue;
                }

                $candidates[] = array(
                    'tech_id'  => $tech_id,
                    'distance' => round( $distance, 2 ),
                );
            }
        }

        // Si nadie califica por distancia+horario
        if ( empty( $candidates ) ) {
            // Distinción útil:
            // - Puede ser que haya techs por radio pero ninguno con schedule.
            // Para saberlo, hacemos un segundo pass rápido: ¿hay alguien por radio sin schedule?
            $has_by_radius = $this->any_tech_in_radius( $client_lat, $client_lng );
            $reason = $has_by_radius ? 'no_tech_schedule' : 'out_of_service_area';

            $this->save_no_coverage( $draft_token, $reason );
            return $this->no_coverage_result( $reason );
        }

        usort( $candidates, function( $a, $b ) {
            return $a['distance'] <=> $b['distance'];
        });

        $ok_tech_ids = array();
        $distances   = array();

        foreach ( $candidates as $c ) {
            $ok_tech_ids[] = (int) $c['tech_id'];
            $distances[(int)$c['tech_id']] = (float) $c['distance'];
        }

        // Guardar coverage (nota: tú antes lo guardabas bajo 'address' slice)
        $this->draft_repo->upsert(
            $draft_token,
            'address',
            array(
                'coverage' => array(
                    'ok'         => true,
                    'yes'        => 1,
                    'tech_ids'   => $ok_tech_ids,
                    'distances'  => $distances,
                    'checked_at' => current_time( 'mysql' ),
                    'location'   => $location,
                    'reason'     => '',
                )
            )
        );

        return array(
            'has_tech'  => true,
            'tech_ids'  => $ok_tech_ids,
            'distances' => $distances,
        );
    }

    /* =======================================================
     * Helpers
     * ======================================================= */

    /**
     * ✅ Tech tiene al menos 1 bloque semanal usable
     */
    private function tech_has_any_schedule( int $tech_id ): bool {

        $blocks = $this->availability_repo->get_week( $tech_id );
        if ( empty( $blocks ) || ! is_array( $blocks ) ) return false;

        foreach ( $blocks as $b ) {
            if ( (int) ( $b['is_available'] ?? 0 ) !== 1 ) continue;

            $st = (string) ( $b['start_time'] ?? '' );
            $en = (string) ( $b['end_time'] ?? '' );
            if ( $st === '' || $en === '' ) continue;

            return true;
        }

        return false;
    }

    /**
     * ¿Existe algún tech aprobado con radio que cubra el punto (aunque no tenga schedule)?
     * Esto nos permite distinguir no_tech_schedule vs out_of_service_area.
     */
    private function any_tech_in_radius( float $client_lat, float $client_lng ): bool {

        $tech_ids = $this->get_all_staff_user_ids();

        foreach ( $tech_ids as $tech_id ) {

            $tech_id = (int) $tech_id;
            if ( $tech_id <= 0 ) continue;

            $profile = $this->profile_repo->get_profile( $tech_id );
            if ( (string)($profile['status'] ?? '') !== 'approved' ) continue;

            $radius = (float) ($profile['radius'] ?? 0);
            if ( $radius <= 0 ) continue;

            $geo = ( isset($profile['geo']) && is_array($profile['geo']) ) ? $profile['geo'] : array();
            $tech_lat = isset($geo['lat']) ? (float) $geo['lat'] : 0.0;
            $tech_lng = isset($geo['lng']) ? (float) $geo['lng'] : 0.0;
            if ( $tech_lat === 0.0 && $tech_lng === 0.0 ) continue;

            $distance = $this->distance_miles( $tech_lat, $tech_lng, $client_lat, $client_lng );
            if ( $distance <= $radius ) return true;
        }

        return false;
    }

    /**
     * ✅ Siempre devuelve array de IDs (int)
     */
    private function get_all_staff_user_ids(): array {

        $q = new WP_User_Query( array(
            'role'   => 'bb_staff',
            'fields' => 'ID',
            'number' => -1,
        ) );

        $ids = $q->get_results();
        if ( ! is_array($ids) ) return array();

        return array_values(array_filter(array_map('intval', $ids)));
    }

    private function distance_miles( float $lat1, float $lng1, float $lat2, float $lng2 ): float {

        $earth_radius = 3958.8; // miles

        $dLat = deg2rad( $lat2 - $lat1 );
        $dLng = deg2rad( $lng2 - $lng1 );

        $a = sin( $dLat / 2 ) ** 2 +
             cos( deg2rad( $lat1 ) ) *
             cos( deg2rad( $lat2 ) ) *
             sin( $dLng / 2 ) ** 2;

        $c = 2 * asin( min( 1, sqrt( $a ) ) );

        return $earth_radius * $c;
    }

    private function save_no_coverage( string $draft_token, string $reason ): void {

        $this->draft_repo->upsert(
            $draft_token,
            'address',
            array(
                'coverage' => array(
                    'ok'         => false,
                    'yes'        => 0,
                    'reason'     => $reason,
                    'checked_at' => current_time( 'mysql' ),
                )
            )
        );
    }

    private function no_coverage_result( string $reason ): array {
        return array(
            'has_tech' => false,
            'reason'   => $reason,
            'tech_ids' => array(),
        );
    }
}