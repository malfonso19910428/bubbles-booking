<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * BB_Job_Builder
 *
 * Construye un "Job" genérico a partir del state del wizard.
 * NO depende de UI, NO usa $_POST, NO imprime HTML.
 */
final class BB_Job_Builder {

    /** @var BB_Services_Service|null */
    private $services_service;

    public function __construct( $services_service = null ) {
        $this->services_service = ( $services_service instanceof BB_Services_Service ) ? $services_service : null;
    }

    public function build( array $state ): array {

        $job = array(
            'subject'  => $this->build_subject( $state ),
            'service'  => $this->build_service( $state ),
            'addons'   => $this->build_addons( $state ),
            'schedule' => $this->build_schedule( $state ),
            'location' => $this->build_location( $state ),
            'customer' => $this->build_customer( $state ),
            'cost'     => array(),
            'duration' => array(),
        );

        $job['cost']     = $this->calculate_cost( $job );
        $job['duration'] = $this->calculate_duration( $job );

        return $job;
    }

    private function build_subject( array $state ): ?array {
        if ( empty( $state['vehicle'] ) || ! is_array( $state['vehicle'] ) ) {
            return null;
        }

        $label = $state['vehicle']['label'] ?? '';
        if ( $label === '' ) {
            $y  = $state['vehicle']['year']  ?? '';
            $m  = $state['vehicle']['make']  ?? '';
            $mo = $state['vehicle']['model'] ?? '';
            $c  = $state['vehicle']['color'] ?? '';
            $label = trim("$y $m $mo");
            if ( $c !== '' ) $label .= " ($c)";
        }

        return array(
            'type'  => 'vehicle',
            'label' => (string) $label,
            'meta'  => $state['vehicle'],
        );
    }

    /**
     * Normaliza fila de servicio desde DB/Repo a formato canónico del wizard.
     * Maneja base_price -> price.
     */
    private function normalize_service_row( array $row, int $id ): array {
        $name = (string) ( $row['name'] ?? $row['label'] ?? '' );

        // ✅ clave real de tu tabla: base_price
        $price = 0.0;
        if ( isset( $row['price'] ) ) {
            $price = (float) $row['price'];
        } elseif ( isset( $row['base_price'] ) ) {
            $price = (float) $row['base_price'];
        }

        // duration
        $duration = 0;
        if ( isset( $row['duration_minutes'] ) ) {
            $duration = (int) $row['duration_minutes'];
        } elseif ( isset( $row['duration'] ) ) {
            $duration = (int) $row['duration'];
        }

        // Meta: preserva todo, pero asegura llaves útiles para duration/cost
        $meta = $row;
        $meta['id'] = $id;
        if ( ! isset( $meta['duration_minutes'] ) && $duration > 0 ) {
            $meta['duration_minutes'] = $duration;
        }
        if ( ! isset( $meta['base_price'] ) && isset( $row['price'] ) ) {
            $meta['base_price'] = (float) $row['price'];
        }

        return array(
            'id'    => $id,
            'name'  => $name,
            'price' => (float) $price,
            'meta'  => $meta,
        );
    }

    /**
     * ✅ Service:
     * - Soporta state['package'] (meta completo)
     * - Soporta bb_package/selected_package/vehicle_bb_package (solo ID)
     *
     * FIX: si solo hay ID, ahora consulta el dominio (BB_Services_Service->get_by_id)
     * y normaliza base_price -> price.
     */
    private function build_service( array $state ): ?array {

        // 1) Si ya existe meta completo, úsalo (pero normaliza price/base_price por si acaso)
        if ( isset( $state['package'] ) && is_array( $state['package'] ) ) {
            $id = (int) ( $state['package']['id'] ?? 0 );
            if ( $id > 0 ) {
                return $this->normalize_service_row( $state['package'], $id );
            }
        }

        // 2) Resolver ID desde state
        $id = 0;

        if ( isset( $state['bb_package'] ) ) {
            $id = (int) $state['bb_package'];
        } elseif ( isset( $state['selected_package'] ) ) {
            $id = (int) $state['selected_package'];
        } elseif ( isset( $state['vehicle_bb_package'] ) ) {
            $id = (int) $state['vehicle_bb_package'];
        }

        if ( $id <= 0 ) {
            return null;
        }

        // 3) Si tenemos el dominio inyectado, cargar por ID (✅ solución real)
        if ( $this->services_service instanceof BB_Services_Service ) {
            $row = $this->services_service->get_by_id( $id );
            if ( is_array( $row ) && ! empty( $row ) ) {
                return $this->normalize_service_row( $row, $id );
            }
        }

        // 4) Fallback mínimo: no rompe UI, pero puede verse vacío si no hay DI
        return array(
            'id'    => $id,
            'name'  => '',
            'price' => 0.0,
            'meta'  => array( 'id' => $id ),
        );
    }

    /**
     * ✅ Addons:
     * - Si existe state['addons_items'], lo usa (objetos completos)
     * - Si no, usa state['addons'] (IDs u objetos)
     */
    private function build_addons( array $state ): array {

        $source = array();

        if ( isset( $state['addons_items'] ) && is_array( $state['addons_items'] ) && ! empty( $state['addons_items'] ) ) {
            $source = $state['addons_items'];
        } elseif ( isset( $state['addons'] ) && is_array( $state['addons'] ) && ! empty( $state['addons'] ) ) {
            $source = $state['addons'];
        } else {
            return array();
        }

        $dedupe = array();

        foreach ( $source as $addon ) {

            if ( is_scalar( $addon ) ) {
                $id = (int) $addon;
                if ( $id <= 0 ) continue;

                $dedupe[ (string) $id ] = array(
                    'id'    => $id,
                    'name'  => '',
                    'price' => 0.0,
                    'meta'  => array( 'id' => $id ),
                );
                continue;
            }

            if ( is_array( $addon ) ) {
                $id = (int) ( $addon['id'] ?? 0 );
                if ( $id <= 0 ) continue;

                $key = (string) $id;

                $dedupe[ $key ] = array(
                    'id'    => $id,
                    'name'  => (string) ( $addon['name'] ?? $addon['label'] ?? '' ),
                    'price' => (float)  ( $addon['price'] ?? 0 ),
                    'meta'  => $addon,
                );
            }
        }

        return array_values( $dedupe );
    }

    private function build_schedule( array $state ): array {
        if ( isset( $state['date'] ) && is_array( $state['date'] ) ) {
            return array(
                'date' => $state['date']['date'] ?? null,
                'time' => $state['date']['time'] ?? null,
            );
        }

        return array(
            'date' => $state['date'] ?? null,
            'time' => $state['time'] ?? null,
        );
    }

    private function build_location( array $state ): array {
        if ( empty( $state['address'] ) || ! is_array( $state['address'] ) ) {
            return array();
        }

        return array(
            'address' => $state['address']['formatted'] ?? '',
            'lat'     => $state['address']['lat'] ?? null,
            'lng'     => $state['address']['lng'] ?? null,
        );
    }

    private function build_customer( array $state ): array {
        return array(
            'name'  => $state['customer']['name']  ?? null,
            'email' => $state['customer']['email'] ?? null,
            'phone' => $state['customer']['phone'] ?? null,
        );
    }

    private function calculate_cost( array $job ): array {

        $base   = (float) ( $job['service']['price'] ?? 0 );
        $addons = 0.0;

        foreach ( (array) $job['addons'] as $a ) {
            $addons += (float) ( $a['price'] ?? 0 );
        }

        return array(
            'base'   => $base,
            'addons' => $addons,
            'total'  => $base + $addons,
        );
    }

    private function calculate_duration( array $job ): array {

        $minutes = 0;

        if ( ! empty( $job['service']['meta']['duration_minutes'] ) ) {
            $minutes += (int) $job['service']['meta']['duration_minutes'];
        } elseif ( ! empty( $job['service']['meta']['duration'] ) ) {
            $minutes += (int) $job['service']['meta']['duration'];
        }

        foreach ( (array) $job['addons'] as $a ) {
            if ( ! empty( $a['meta']['duration_minutes'] ) ) {
                $minutes += (int) $a['meta']['duration_minutes'];
            } elseif ( ! empty( $a['meta']['duration'] ) ) {
                $minutes += (int) $a['meta']['duration'];
            }
        }

        return array(
            'minutes' => $minutes,
        );
    }
}
