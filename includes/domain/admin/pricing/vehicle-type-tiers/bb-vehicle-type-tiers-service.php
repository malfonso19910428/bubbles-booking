<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('BB_Vehicle_Type_Tiers_Service') ) {

class BB_Vehicle_Type_Tiers_Service {

    protected BB_Vehicle_Type_Tiers_Repo $repo;

    public function __construct( BB_Vehicle_Type_Tiers_Repo $repo ) {
        $this->repo = $repo;
    }

    public function list_all(): array {
        return $this->repo->list_all();
    }

    /**
     * Resuelve tier_id activo para un vehicle_type (slug).
     */
    public function resolve_tier_id( string $vehicle_type ): int {
        $vehicle_type = sanitize_key( $vehicle_type );
        if ( $vehicle_type === '' ) return 0;

        return (int) $this->repo->get_tier_id( $vehicle_type );
    }

    /**
     * ✅ Alias para el Pricing Engine (compatibilidad).
     */
    public function get_active_tier_id_by_vehicle_type( string $vehicle_type ): int {
        return $this->resolve_tier_id( $vehicle_type );
    }

    /**
     * Guarda mapping masivo:
     * $map = ['sedan' => tier_id, 'suv' => tier_id, ...]
     * $active_map = ['sedan' => 1/true, 'suv' => 0/false, ...]
     */
    public function save_bulk( array $map, array $active_map = array() ): void {

        $default_order = array(
            'sedan'     => 10,
            'coupe'     => 20,
            'hatchback' => 30,
            'suv'       => 40,
            'pickup'    => 50,
            'van'       => 60,
            'other'     => 999,
        );

        if ( empty($map) || ! is_array($map) ) return;

        foreach ( $map as $vehicle_type => $tier_id ) {

            $vt  = sanitize_key( (string) $vehicle_type );
            $tid = (int) $tier_id;

            if ( $vt === '' || $tid <= 0 ) continue;

            $is_active = array_key_exists($vt, $active_map) ? (int) ( ! empty($active_map[$vt]) ) : 1;
            $sort_order = isset($default_order[$vt]) ? (int) $default_order[$vt] : 100;

            $this->repo->upsert( $vt, $tid, $is_active, $sort_order );
        }
    }
}

} // end if !class_exists
