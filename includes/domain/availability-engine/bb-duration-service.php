<?php
// FILE: includes/domain/availability-engine/bb-duration-service.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Calcula la duración total del trabajo (service + addons) en minutos.
 * NOTA: El BUFFER se aplica en el Availability Engine (regla global),
 * para que esta clase sea “pura” (solo catálogo).
 */
final class BB_Duration_Service {

    private BB_Services_Service $services;
    private BB_Addons_Service   $addons;

    public function __construct(
        BB_Services_Service $services,
        BB_Addons_Service $addons
    ) {
        $this->services = $services;
        $this->addons   = $addons;
    }

    public function get_total_duration_min( array $draft_state ): int {

        $total = 0;

        // ======================
        // SERVICE
        // ======================
        $service_id = $this->get_service_id( $draft_state );

        if ( $service_id > 0 ) {
            $service = $this->services->get_by_id( $service_id );
            if ( is_array( $service ) ) {
                $total += max( 0, (int) ( $service['duration_minutes'] ?? 0 ) );
            }
        }

        // ======================
        // ADDONS (pueden ser varios)
        // ======================
        $addon_ids = $this->get_addon_ids( $draft_state );

        foreach ( $addon_ids as $addon_id ) {
            $addon_id = (int) $addon_id;
            if ( $addon_id <= 0 ) continue;

            $addon = $this->addons->get_by_id( $addon_id );
            if ( ! is_array( $addon ) ) continue;

            // tu Addons usa "duration" (pero soportamos duration_minutes también)
            $dur = 0;
            if ( isset( $addon['duration_minutes'] ) ) {
                $dur = (int) $addon['duration_minutes'];
            } elseif ( isset( $addon['duration'] ) ) {
                $dur = (int) $addon['duration'];
            }

            if ( $dur > 0 ) $total += $dur;
        }

        return ( $total > 0 ) ? $total : 60;
    }

    // ======================
    // Helpers (state)
    // ======================

    private function get_service_id( array $s ): int {
        if ( isset( $s['package']['service_id'] ) ) {
            return (int) $s['package']['service_id'];
        }
        if ( isset( $s['service_id'] ) ) {
            return (int) $s['service_id'];
        }
        return 0;
    }

    private function get_addon_ids( array $s ): array {

        // recomendado: $state['addons']['selected'] = [1,2,3]
        if ( isset( $s['addons']['selected'] ) && is_array( $s['addons']['selected'] ) ) {
            return array_values( array_filter( array_map( 'intval', $s['addons']['selected'] ) ) );
        }

        // alternativa: $state['addons'] = [1,2,3]
        if ( isset( $s['addons'] ) && is_array( $s['addons'] ) ) {
            // si addons es array de IDs
            $is_ids = true;
            foreach ( $s['addons'] as $x ) {
                if ( is_array( $x ) ) { $is_ids = false; break; }
            }
            if ( $is_ids ) {
                return array_values( array_filter( array_map( 'intval', $s['addons'] ) ) );
            }
        }

        return array();
    }
}
