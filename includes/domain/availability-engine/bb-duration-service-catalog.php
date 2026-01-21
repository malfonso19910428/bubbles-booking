<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class BB_Duration_Service_Catalog implements BB_Duration_Service {

    private BB_Services_Repo $services_repo;

    /** @var object|null  puede ser BB_Addons_Repo si lo tienes */
    private $addons_repo;

    /**
     * @param BB_Services_Repo $services_repo
     * @param object|null $addons_repo  (opcional) repo de addons con get_by_id()
     */
    public function __construct( BB_Services_Repo $services_repo, $addons_repo = null ) {
        $this->services_repo = $services_repo;
        $this->addons_repo   = $addons_repo;
    }

    public function get_total_duration_min(
        array $draft_state,
        BB_Availability_Rules_Service $rules
    ): int {

        $total = 0;

        // ==========================
        // 1) Service duration
        // ==========================
        $service_id = $this->extract_service_id( $draft_state );

        if ( $service_id > 0 ) {
            $service = $this->services_repo->get_by_id( $service_id );
            if ( is_array($service) ) {
                $total += max(0, (int)($service['duration_minutes'] ?? 0));
            }
        }

        // ==========================
        // 2) Addons duration
        // ==========================
        $addon_items_or_ids = $this->extract_addons( $draft_state );

        if ( is_array($addon_items_or_ids) ) {
            foreach ( $addon_items_or_ids as $a ) {

                // Caso A: ya viene duration en el draft (mejor performance)
                if ( is_array($a) ) {
                    $dur = isset($a['duration_minutes']) ? (int)$a['duration_minutes'] : 0;
                    if ( $dur > 0 ) {
                        $total += $dur;
                        continue;
                    }

                    // si viene id dentro del array
                    $addon_id = (int)($a['id'] ?? 0);
                    if ( $addon_id > 0 ) {
                        $total += $this->addon_duration_from_repo( $addon_id );
                    }
                    continue;
                }

                // Caso B: viene como lista de IDs
                $addon_id = (int)$a;
                if ( $addon_id > 0 ) {
                    $total += $this->addon_duration_from_repo( $addon_id );
                }
            }
        }

        // ==========================
        // fallback defensivo
        // ==========================
        if ( $total <= 0 ) return 60;

        return $total;
    }

    // ----------------------------
    // Extractors (adaptables al state)
    // ----------------------------

    private function extract_service_id( array $s ): int {

        // Ajusta aquí cuando confirmemos tu estructura exacta.
        // Dejo varias rutas comunes:
        $candidates = array(
            $s['package']['service_id'] ?? null,
            $s['service']['id'] ?? null,
            $s['service_id'] ?? null,
            $s['package_id'] ?? null,
        );

        foreach ( $candidates as $v ) {
            $id = (int)$v;
            if ( $id > 0 ) return $id;
        }

        return 0;
    }

    /**
     * Devuelve:
     * - array de IDs, o
     * - array de arrays (cada addon con duration_minutes o id)
     */
    private function extract_addons( array $s ): array {

        // rutas comunes:
        if ( isset($s['addons']['selected']) && is_array($s['addons']['selected']) ) {
            return $s['addons']['selected'];
        }

        if ( isset($s['addons']['ids']) && is_array($s['addons']['ids']) ) {
            return $s['addons']['ids'];
        }

        if ( isset($s['addons']) && is_array($s['addons']) ) {
            // a veces addons ya es lista plana
            return $s['addons'];
        }

        return array();
    }

    private function addon_duration_from_repo( int $addon_id ): int {

        // Si no tienes addons_repo todavía, devuelve 0.
        if ( ! is_object($this->addons_repo) ) return 0;

        if ( ! method_exists($this->addons_repo, 'get_by_id') ) return 0;

        $addon = $this->addons_repo->get_by_id( $addon_id );
        if ( ! is_array($addon) ) return 0;

        // usa duration_minutes (si tu tabla usa otro nombre lo cambiamos)
        $dur = (int)($addon['duration_minutes'] ?? 0);
        return max(0, $dur);
    }
}
