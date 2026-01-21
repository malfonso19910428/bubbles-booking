<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Job_Targets_Service {

    private BB_Job_Targets_Repo $repo;

    public function __construct( BB_Job_Targets_Repo $repo ) {
        $this->repo = $repo;
    }

    public function get_all( string $industry = '' ): array {
        return $this->repo->get_all( $industry );
    }

    public function get_by_id( int $id ): ?array {
        return $this->repo->get_by_id( $id );
    }

    /**
     * Genera slug desde label y asegura unicidad por industry.
     */
    public function build_unique_slug( string $industry, string $label, int $exclude_id = 0 ): string {
        $industry = sanitize_key($industry);
        $base     = sanitize_title( $label );
        if ( $base === '' ) $base = 'target';

        $slug = $base;
        $n = 2;
        while ( $this->repo->slug_exists($industry, $slug, $exclude_id) ) {
            $slug = $base . '-' . $n;
            $n++;
            if ( $n > 200 ) break; // safety
        }
        return $slug;
    }

    public function upsert( array $data ): int {
        $id       = isset($data['id']) ? (int)$data['id'] : 0;
        $industry = isset($data['industry']) ? sanitize_key($data['industry']) : 'auto';
        $label    = isset($data['label']) ? sanitize_text_field($data['label']) : '';

        // ✅ slug auto SIEMPRE (admin no lo setea)
        $data['slug'] = $this->build_unique_slug( $industry, $label, $id );

        // ✅ sort_order auto en creates (si viene 0)
        if ( empty($data['sort_order']) ) {
            $data['sort_order'] = $this->repo->get_next_sort_order( $industry );
        }

        return $this->repo->upsert( $data );
    }

    public function update_fields( int $id, array $fields ): bool {
        return $this->repo->update_fields( $id, $fields );
    }

    public function delete( int $id ): bool {
        return $this->repo->delete( $id );
    }

    public function seed_defaults_if_empty(): void {
        // tu lógica igual (puede quedarse), solo que ya no hace falta slug manual
        // si quieres, la dejamos tal cual por ahora.
        if ( ! $this->repo->table_exists() ) return;
        if ( $this->repo->count_all() > 0 ) return;

        $defaults = array(
            array('industry'=>'auto','label'=>'Sedan','scope_level'=>'S','sort_order'=>10,'is_active'=>1),
            array('industry'=>'auto','label'=>'SUV','scope_level'=>'M','sort_order'=>20,'is_active'=>1),
            array('industry'=>'auto','label'=>'Truck / Pickup','scope_level'=>'L','sort_order'=>30,'is_active'=>1),
            array('industry'=>'auto','label'=>'Van','scope_level'=>'XL','sort_order'=>40,'is_active'=>1),
        );

        foreach ( $defaults as $row ) {
            $this->upsert( $row );
        }
    }
}
