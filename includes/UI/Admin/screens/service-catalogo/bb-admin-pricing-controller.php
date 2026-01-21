<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Admin_Pricing_Controller {

    protected BB_Pricing_Tiers_Service $tiers_service;
    protected BB_Services_Service $services_service;

    // ✅ NUEVO: vehicle type -> tier
    

    protected bool $tiers_saved = false;
    protected bool $vehicle_map_saved = false;

    public function __construct(
        BB_Pricing_Tiers_Service $tiers_service,
        BB_Services_Service $services_service,
    
    ) {
        $this->tiers_service              = $tiers_service;
        $this->services_service           = $services_service;
      
    }

    public function handle_post(): void {

        if ( ! current_user_can('manage_options') ) return;

        $action = isset($_POST['bb_pricing_action'])
            ? sanitize_key( wp_unslash($_POST['bb_pricing_action']) )
            : '';

        // ✅ Solo manejamos acciones conocidas
        if ( ! in_array($action, array('save_tiers', 'save_vehicle_type_tiers'), true) ) {
            return;
        }

        // ✅ Nonce único del tab Pricing
        if (
            ! isset($_POST['bb_pricing_nonce']) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['bb_pricing_nonce']) ), 'bb_pricing_save_tiers' )
        ) {
            return;
        }

        // =========================
        // 1) Guardar TIERS
        // =========================
        if ( $action === 'save_tiers' ) {

            $tiers_raw = ( isset($_POST['tiers']) && is_array($_POST['tiers']) ) ? $_POST['tiers'] : array();
            $tiers = array();

            foreach ( $tiers_raw as $i => $t ) {

                $id    = isset($t['id']) ? (int) $t['id'] : 0;
                $slug  = isset($t['slug']) ? sanitize_key( wp_unslash($t['slug']) ) : '';
                $label = isset($t['label']) ? sanitize_text_field( wp_unslash($t['label']) ) : '';
                $sort  = isset($t['sort_order']) ? (int) $t['sort_order'] : 10;

                // ✅ checkbox: si no viene, OFF
                $is_active = ! empty($t['is_active']) ? 1 : 0;

                // ✅ Disable (safe remove) gana siempre
                if ( ! empty($t['_delete']) ) {
                    $is_active = 0;
                }

                $default_add = isset($t['default_add']) ? (float) $t['default_add'] : 0.0;
                if ( $default_add < 0 ) $default_add = 0.0;

                $tiers[] = array(
                    'id'          => $id,
                    'slug'        => $slug,
                    'label'       => $label,
                    'sort_order'  => $sort,
                    'is_active'   => $is_active,
                    'default_add' => round($default_add, 2),
                );
            }

            $this->tiers_service->save_many( $tiers );
            $this->tiers_saved = true;

            return;
        }

        // =========================
        // 2) Guardar VEHICLE TYPE -> TIER (tabla)
        // =========================
        if ( $action === 'save_vehicle_type_tiers' ) {

            $map_raw = ( isset($_POST['vehicle_map']) && is_array($_POST['vehicle_map']) )
                ? $_POST['vehicle_map']
                : array();

            $active_raw = ( isset($_POST['vehicle_active']) && is_array($_POST['vehicle_active']) )
                ? $_POST['vehicle_active']
                : array();

            $map = array();
            $active_map = array();

            foreach ( $map_raw as $vehicle_type => $tier_id ) {
                $vt = sanitize_key( $vehicle_type );
                $tid = (int) $tier_id;

                if ( $vt === '' ) continue;

                // Permitimos guardar aunque tier_id sea 0 si quieres “vaciarlo”.
                // Pero el service normalmente ignora <=0.
                $map[$vt] = $tid;

                // checkbox: si no viene, OFF
                $active_map[$vt] = ! empty($active_raw[$vt]) ? 1 : 0;
            }

            $this->vehicle_type_tiers_service->save_bulk( $map, $active_map );

            $this->vehicle_map_saved = true;
            return;
        }
    }

    public function get_view_data(): array {

    $notes = array(
        'Adjusted Service Price = Base Price + Tier Price Add.',
        'This screen does not include add-ons. Add-ons are calculated during booking.',
    );

    $tiers_all = $this->tiers_service->list_all();
    if ( ! is_array( $tiers_all ) ) {
        $tiers_all = array();
    }

    // ✅ Activos estrictos
    $tiers_active = array_values( array_filter( $tiers_all, function( $t ) {
        return (int) ( $t['is_active'] ?? 0 ) === 1;
    } ) );

    // ✅ Método estándar
    $services = $this->services_service->get_all();
    if ( ! is_array( $services ) ) {
        $services = array();
    }

    // Preview matrix (solo default_add por ahora)
    $matrix = array();

    foreach ( $services as $s ) {
        $sid  = (int) ( $s['id'] ?? 0 );
        $base = (float) ( $s['base_price'] ?? 0 );

        if ( $sid <= 0 ) {
            continue;
        }

        foreach ( $tiers_active as $t ) {
            $tid = (int) ( $t['id'] ?? 0 );
            $add = (float) ( $t['default_add'] ?? 0 );

            if ( $tid <= 0 ) {
                continue;
            }

            $matrix[ $sid ][ $tid ] = round( $base + $add, 2 );
        }
    }

    return array(
        'tiers_saved'  => (bool) $this->tiers_saved,

        'tiers'        => $tiers_all,
        'tiers_active' => $tiers_active,

        'services'     => $services,
        'matrix'       => $matrix,

        'notes'        => $notes,
    );
}

}
