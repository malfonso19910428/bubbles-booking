<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class BB_Addons_Service {

    /** @var BB_Addons_Repo */
    protected BB_Addons_Repo $repo;

    public function __construct( BB_Addons_Repo $repo ) {
        $this->repo = $repo;
    }

    /**
     * Devuelve todos los add-ons (para admin)
     */
    public function get_all(): array {
        $all = $this->repo->get_all();
        return is_array( $all ) ? $all : array();
    }

    /**
     * Devuelve solo add-ons activos, pensados para el wizard/frontend.
     */
    public function get_active_for_wizard(): array {
        $all = $this->get_all();
        if ( empty( $all ) ) return array();

        return array_values(
            array_filter(
                $all,
                static function( $row ) {
                    return is_array( $row ) && ! empty( $row['active'] );
                }
            )
        );
    }

    /**
     * Obtiene un add-on por ID (aunque esté inactivo).
     * Requiere que el repo implemente get_by_id().
     */
    public function get_by_id( int $id ): ?array {
        $id = (int) $id;
        if ( $id <= 0 ) return null;

        if ( ! method_exists( $this->repo, 'get_by_id' ) ) {
            return null;
        }

        $row = $this->repo->get_by_id( $id );
        return is_array( $row ) ? $row : null;
    }

    /**
     * Crea un add-on desde datos del formulario (admin).
     *
     * @return int|false
     */
    public function create_from_form( array $data ) {

        $name        = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( wp_unslash( $data['description'] ) ) : '';
        $price       = isset( $data['price'] ) ? (float) $data['price'] : 0.0;
        $duration    = isset( $data['duration'] ) ? (int) $data['duration'] : 0;
        $active      = ! empty( $data['active'] ) ? 1 : 0;

        if ( $name === '' ) {
            return false;
        }

        if ( $price < 0 ) $price = 0.0;
        if ( $duration < 0 ) $duration = 0;

        return $this->repo->create( array(
            'name'        => $name,
            'description' => $description,
            'price'       => $price,
            'duration'    => $duration,
            'active'      => $active,
        ) );
    }
}
