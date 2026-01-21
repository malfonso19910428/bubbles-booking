<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('BB_Pricing_Engine') ) {

error_log('BB_PRICING_ENGINE LOADED: ' . __FILE__);

class BB_Pricing_Engine {

    protected BB_Services_Service $services_service;
    protected BB_Job_Targets_Service $job_targets_service;

    // Overrides por service/tier (opcional)
   // protected BB_Service_Tier_Prices_Service $service_tier_prices_service;

    // Default add por tier (bb_pricing_tiers)
    protected BB_Pricing_Tiers_Service $pricing_tiers_service;

    public function __construct(
        BB_Services_Service $services_service,
        BB_Job_Targets_Service $job_targets_service,
       // BB_Service_Tier_Prices_Service $service_tier_prices_service,
        BB_Pricing_Tiers_Service $pricing_tiers_service
    ) {
        $this->services_service            = $services_service;
        $this->job_targets_service         = $job_targets_service;
       // $this->service_tier_prices_service = $service_tier_prices_service;
        $this->pricing_tiers_service       = $pricing_tiers_service;

        $this->log('Engine constructed', array(
            'services_service' => is_object($services_service) ? 'ok' : 'no',
            'job_targets_service' => is_object($job_targets_service) ? 'ok' : 'no',
           // 'service_tier_prices_service' => is_object($service_tier_prices_service) ? 'ok' : 'no',
            'pricing_tiers_service' => is_object($pricing_tiers_service) ? 'ok' : 'no',
        ));
    }

    protected function log( string $msg, array $ctx = array() ): void {
        if ( ! defined('BB_PRICING_DEBUG') || BB_PRICING_DEBUG !== true ) return;

        $prefix = '[BB_PRICING] ';
        $line   = $prefix . $msg;

        if ( ! empty($ctx) ) {
            $safe = $ctx;
            foreach ( $safe as $k => $v ) {
                if ( is_object($v) ) {
                    $safe[$k] = '(object)';
                } elseif ( is_array($v) ) {
                    $safe[$k] = (count($v) > 40) ? ('(array count=' . count($v) . ')') : $v;
                }
            }
            $line .= ' | ' . wp_json_encode($safe);
        }

        error_log( $line );
    }

    protected function adjusted_price( float $base, float $add ): float {
        $base = max(0, (float) $base);
        $add  = max(0, (float) $add);
        return round($base + $add, 2);
    }

    /**
     * Helper: obtener job target por ID (compatible con varios nombres de método).
     */
    protected function get_job_target_by_id( int $job_target_id ) {
        if ( $job_target_id <= 0 ) return null;

        $jt = null;

        if ( method_exists($this->job_targets_service, 'get_by_id') ) {
            $jt = $this->job_targets_service->get_by_id( $job_target_id );
        } elseif ( method_exists($this->job_targets_service, 'get') ) {
            $jt = $this->job_targets_service->get( $job_target_id );
        } elseif ( method_exists($this->job_targets_service, 'find_by_id') ) {
            $jt = $this->job_targets_service->find_by_id( $job_target_id );
        }

        return $jt;
    }

    /**
     * ✅ Nuevo: tier_id directo desde Job Target.
     * Busca: pricing_tier_id (preferido) o tier_id (fallback).
     */
    public function tier_id_from_job_target_id( int $job_target_id ): int {

        if ( $job_target_id <= 0 ) {
            $this->log('tier_id_from_job_target_id: invalid job_target_id', array('job_target_id' => $job_target_id));
            return 0;
        }

        $jt = $this->get_job_target_by_id( $job_target_id );

        if ( empty($jt) ) {
            $this->log('tier_id_from_job_target_id: job target not found', array('job_target_id' => $job_target_id));
            return 0;
        }

        // Debug snapshot (solo campos relevantes)
        if ( is_array($jt) ) {
            $snapshot = array(
                'id' => (int)($jt['id'] ?? 0),
                'slug' => (string)($jt['slug'] ?? ''),
                'label' => (string)($jt['label'] ?? ''),
                'industry' => (string)($jt['industry'] ?? ''),
                'pricing_tier_id' => (int)($jt['pricing_tier_id'] ?? 0),
                'tier_id' => (int)($jt['tier_id'] ?? 0),
                'is_active' => (int)($jt['is_active'] ?? 1),
                'updated_at' => (string)($jt['updated_at'] ?? ''),
            );
        } else {
            $snapshot = '(object)';
        }

        $tier_id = 0;
        if ( is_array($jt) ) {
            $tier_id = (int) ( $jt['pricing_tier_id'] ?? $jt['tier_id'] ?? 0 );
        } else {
            // por si te devuelven object
            $tier_id = (int) ( $jt->pricing_tier_id ?? $jt->tier_id ?? 0 );
        }

        $tier_id = max(0, $tier_id);

        $this->log('Resolved tier_id from job_target (direct)', array(
            'job_target_id' => $job_target_id,
            'tier_id'       => $tier_id,
            'job_target'    => $snapshot,
        ));

        return $tier_id;
    }

    /**
     * ✅ default_add por tier_id (desde bb_pricing_tiers)
     */
    protected function default_add_for_tier_id( int $tier_id ): float {
        if ( $tier_id <= 0 ) {
            $this->log('default_add_for_tier_id: tier_id <= 0', array('tier_id' => $tier_id));
            return 0.0;
        }

        $method = 'none';
        $val    = 0.0;

        // Ideal: método directo en service
        if ( method_exists($this->pricing_tiers_service, 'get_default_add_by_id') ) {
            $method = 'get_default_add_by_id';
            $val = (float) $this->pricing_tiers_service->get_default_add_by_id( $tier_id );
            $val = (float) max(0, round($val, 2));
        }
        // Fallback: map
        elseif ( method_exists($this->pricing_tiers_service, 'get_default_add_map') ) {
            $method = 'get_default_add_map';
            $map = $this->pricing_tiers_service->get_default_add_map();
            $val = (float) ( is_array($map) ? ($map[$tier_id] ?? 0.0) : 0.0 );
            $val = (float) max(0, round($val, 2));
        }

        $this->log('default_add_for_tier_id resolved', array(
            'tier_id' => $tier_id,
            'method'  => $method,
            'value'   => $val,
        ));

        return $val;
    }

    /**
     * Devuelve array service_id => final_price para un job_target_id.
     */
    public function prices_for_job_target_id( int $job_target_id ): array {

        $this->log('START prices_for_job_target_id', array('job_target_id' => $job_target_id));

        $tier_id = $this->tier_id_from_job_target_id( $job_target_id );

        // Services para wizard
        $services = $this->services_service->get_active_for_wizard();
        if ( empty($services) || ! is_array($services) ) {
            $this->log('No active services for wizard', array(
                'job_target_id' => $job_target_id,
                'tier_id'       => $tier_id,
            ));
            return array();
        }

        // dump sample keys (una sola vez)
        static $dumped = false;
        if ( ! $dumped && ! empty($services[0]) ) {
            $dumped = true;
            $this->log('Service keys sample', array('keys' => array_keys((array)$services[0])));
        }

        // Default add por tier
        $default_add = $this->default_add_for_tier_id( $tier_id );

        // Overrides por servicio en ese tier
        $adds_by_service = array();
        // if ( $tier_id > 0 && method_exists($this->service_tier_prices_service, 
        // 'get_add_prices_by_tier_id') ) {
        //     $adds_by_service = $this->service_tier_prices_service->get_add_prices_by_tier_id( $tier_id );
        //     if ( ! is_array($adds_by_service) ) $adds_by_service = array();
        // }

    

        $out = array();

        foreach ( (array) $services as $svc ) {

            $service_id = (int) ( $svc['id'] ?? 0 );
            if ( $service_id <= 0 ) continue;

            $base = (float) ( $svc['price'] ?? ($svc['base_price'] ?? 0.0) );

            // default
            $add_used = (float) $default_add;

            // override por service si existe
            if ( $tier_id > 0 && isset($adds_by_service[$service_id]) ) {
                $add_used = (float) $adds_by_service[$service_id];
            }

            if ( $add_used < 0 ) $add_used = 0.0;

            $final = $this->adjusted_price( $base, $add_used );
            $out[$service_id] = $final;

            $this->log('Service pricing', array(
                'service_id'      => $service_id,
                'base'            => $base,
                'tier_id'         => $tier_id,
                'default_add'     => $default_add,
                'override_add'    => isset($adds_by_service[$service_id]) ? (float)$adds_by_service[$service_id] : null,
                'add_used'        => $add_used,
                'final'           => $final,
            ));
        }

        $this->log('DONE prices_for_job_target_id', array(
            'job_target_id' => $job_target_id,
            'tier_id'       => $tier_id,
            'priced_count'  => count($out),
        ));

        return $out;
    }

    /**
     * Precio final para un servicio + job target.
     */
    public function price_for_service_and_job_target(
        int $service_id,
        float $base_price,
        int $job_target_id
    ): float {

        $this->log('START price_for_service_and_job_target', array(
            'job_target_id' => $job_target_id,
            'service_id'    => $service_id,
            'base_price'    => $base_price,
        ));

        $tier_id = $this->tier_id_from_job_target_id( $job_target_id );

        $default_add = $this->default_add_for_tier_id( $tier_id );
        $add_used = $default_add;

        // override si existe
        // if ( $tier_id > 0)  {
        //                 $override = (float) $this->service_tier_prices_service->get_price_add( $service_id, $tier_id );
        //     if ( $override > 0 ) {
        //         $add_used = $override;
        //     }
        // }

        if ( $add_used < 0 ) $add_used = 0.0;

        $final = $this->adjusted_price( (float) $base_price, $add_used );

        $this->log('DONE price_for_service_and_job_target', array(
            'job_target_id' => $job_target_id,
            'service_id'    => $service_id,
            'base'          => (float)$base_price,
            'tier_id'       => $tier_id,
            'default_add'   => $default_add,
            'add_used'      => $add_used,
            'final'         => $final,
        ));

        return $final;
    }
}

} // end if !class_exists
