<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BB_Services_Service' ) ) {

class BB_Services_Service {

    protected BB_Services_Repo $repo;

    public function __construct( BB_Services_Repo $repo ) {
        $this->repo = $repo;
    }

    /**
     * Normaliza rows del repo para asegurar contrato consistente:
     * - base_price siempre existe (fallback a price)
     * - duration_minutes siempre existe (fallback a duration)
     */
    protected function normalize_rows( array $rows ): array {

        foreach ( $rows as &$r ) {

            // base_price
            if ( ! isset($r['base_price']) ) {
                if ( isset($r['price']) ) {
                    $r['base_price'] = (float) $r['price'];
                } else {
                    $r['base_price'] = 0.0;
                }
            } else {
                $r['base_price'] = (float) $r['base_price'];
            }

            // duration_minutes
            if ( ! isset($r['duration_minutes']) && isset($r['duration']) ) {
                $r['duration_minutes'] = (int) $r['duration'];
            } elseif ( isset($r['duration_minutes']) ) {
                $r['duration_minutes'] = (int) $r['duration_minutes'];
            }

            // active (por si viene string)
            if ( isset($r['active']) ) {
                $r['active'] = (int) $r['active'];
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * Devuelve todos los servicios (activos o no), ordenados.
     */
    public function get_all(): array {
        $all = $this->repo->get_all();
        if ( ! is_array($all) ) return array();
        return $this->normalize_rows( $all );
    }

    /**
     * Devuelve los servicios pensados para el wizard/frontend.
     * Por ahora devuelve todo, pero normalizado.
     */
    public function get_active_for_wizard(): array {

        $all = $this->repo->get_all();

        if ( ! is_array( $all ) ) {
            error_log( 'BB_Services_Service::get_active_for_wizard -> get_all() no devolvió array' );
            return array();
        }

        $all = $this->normalize_rows( $all );

        error_log( 'BB_Services_Service::get_active_for_wizard -> total servicios (sin filtrar) = ' . count( $all ) );

        // ✅ Por ahora devolvemos todos (como tú querías)
        return $all;

        /*
        $active = array_values(array_filter($all, function($svc){
            if ( ! isset($svc['active']) ) return true;
            return (int)$svc['active'] === 1;
        }));

        error_log( 'BB_Services_Service::get_active_for_wizard -> activos = ' . count( $active ) );

        return $active;
        */
    }

    public function create_from_form( array $data ): bool {

        $name        = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( wp_unslash( $data['description'] ) ) : '';

        $price = 0.0;
        if ( isset($data['base_price']) ) {
            $price = (float) $data['base_price'];
        } elseif ( isset($data['price']) ) {
            $price = (float) $data['price'];
        }
        if ( $price < 0 ) $price = 0.0;

        $duration = isset( $data['duration'] ) ? (int) $data['duration'] : 0;
        if ( $duration < 0 ) $duration = 0;

        $active = ! empty( $data['active'] ) ? 1 : 0;

        if ( $name === '' ) {
            return false;
        }

        $payload = array(
            'name'             => $name,
            'description'      => $description,
            'base_price'       => $price,
            'duration_minutes' => $duration,
            'active'           => $active,
        );

        return (bool) $this->repo->insert( $payload );
    }

    public function update_from_form( int $id, array $data ): bool {

        if ( $id <= 0 ) {
            return false;
        }

        $name        = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( wp_unslash( $data['description'] ) ) : '';

        $price = 0.0;
        if ( isset($data['base_price']) ) {
            $price = (float) $data['base_price'];
        } elseif ( isset($data['price']) ) {
            $price = (float) $data['price'];
        }
        if ( $price < 0 ) $price = 0.0;

        $duration = isset( $data['duration'] ) ? (int) $data['duration'] : 0;
        if ( $duration < 0 ) $duration = 0;

        $active = ! empty( $data['active'] ) ? 1 : 0;

        if ( $name === '' ) {
            return false;
        }

        $payload = array(
            'name'             => $name,
            'description'      => $description,
            'base_price'       => $price,
            'duration_minutes' => $duration,
            'active'           => $active,
        );

        return (bool) $this->repo->update( $id, $payload );
    }

    public function delete( int $id ): bool {
        return (bool) $this->repo->delete( $id );
    }

    public function get_by_id( int $id ): ?array {
        $row = $this->repo->get_by_id( $id );
        if ( ! is_array($row) ) return null;
        $rows = $this->normalize_rows( array($row) );
        return $rows[0] ?? null;
    }
}

} // class_exists
