<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Pricing_Tiers_Service {

    protected BB_Pricing_Tiers_Repo $repo;

    public function __construct( BB_Pricing_Tiers_Repo $repo ) {
        $this->repo = $repo;
    }

    public function list_all(): array {
        return $this->repo->list_all();
    }

    public function seed_defaults(): void {
        BB_Pricing_Tiers_Repo::seed_defaults();
    }

    public function save_many( array $tiers ): void {
        $clean = array();
        $seen  = array();

        foreach ( $tiers as $t ) {
            if ( ! is_array($t) ) continue;

            $id    = isset($t['id']) ? (int) $t['id'] : 0;
            $slug  = isset($t['slug']) ? $this->normalize_slug( (string) $t['slug'] ) : '';
            $label = isset($t['label']) ? sanitize_text_field( (string) $t['label'] ) : '';
            $sort  = isset($t['sort_order']) ? (int) $t['sort_order'] : 10;

            // ✅ Price Add (+$) default por tier
            $default_add = isset($t['default_add']) ? (float) $t['default_add'] : 0.0;
            if ( $default_add < 0 ) $default_add = 0;
            $default_add = round( $default_add, 2 );

            // checkbox active
            $act = isset($t['is_active']) ? 1 : 0;

            // ✅ soft delete (deactivate)
            $del = ! empty($t['_delete']) ? 1 : 0;
            if ( $del ) {
                $act = 0;
            }

            // Si no hay slug pero hay label, generarlo
            if ( $slug === '' && $label !== '' ) {
                $slug = $this->normalize_slug( sanitize_title($label) );
            }

            if ( $slug === '' || $label === '' ) continue;

            // evitar duplicados por slug en el mismo submit
            if ( isset($seen[$slug]) ) continue;
            $seen[$slug] = true;

            if ( $sort < 0 ) $sort = 0;

            $clean[] = array(
                'id'          => $id,
                'slug'        => $slug,
                'label'       => $label,
                'default_add' => $default_add,
                'sort_order'  => $sort,
                'is_active'   => $act,
            );
        }

        $this->repo->upsert_many( $clean );
    }

    public function normalize_slug( string $slug ): string {
        $slug = sanitize_key( $slug );
        $slug = str_replace('-', '_', $slug);
        return $slug;
    }
        /**
     * ✅ NUEVO: default_add por tier_id
     */
    public function get_default_add_by_id( int $tier_id ): float {
        if ( $tier_id <= 0 ) return 0.0;

        if ( method_exists($this->repo, 'get_default_add_by_id') ) {
            $val = (float) $this->repo->get_default_add_by_id( $tier_id );
            return (float) max(0, round($val, 2));
        }

        // fallback si el repo no tiene método directo
        $map = $this->get_default_add_map();
        $val = (float) ( $map[$tier_id] ?? 0.0 );
        return (float) max(0, round($val, 2));
    }

    /**
     * ✅ NUEVO: mapa [tier_id => default_add] (solo activos)
     */
    public function get_default_add_map(): array {
        if ( method_exists($this->repo, 'get_default_add_map') ) {
            $map = $this->repo->get_default_add_map();
            return is_array($map) ? $map : array();
        }
        return array();
    }

}
