<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Addons_Service {

    /** @var BB_Addons_Repo */
    protected $repo;

    public function __construct( BB_Addons_Repo $repo ) {
        $this->repo = $repo;
    }

    /**
     * Devuelve todos los add-ons (para admin)
     *
     * @return array
     */
    public function get_all(): array {
        return $this->repo->get_all();
    }

    /**
     * Devuelve solo add-ons activos, pensados para el wizard/frontend.
     *
     * @return array
     */
    public function get_active_for_wizard(): array {
        $all = $this->repo->get_all();

        if ( empty( $all ) || ! is_array( $all ) ) {
            return array();
        }

        return array_values(
            array_filter(
                $all,
                function ( $row ) {
                    return ! empty( $row['active'] );
                }
            )
        );
    }

    /**
     * Crea un add-on desde datos del formulario (admin).
     *
     * @param array $data
     * @return int|false
     */
    public function create_from_form( array $data ) {

        $name        = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
        $price       = isset( $data['price'] ) ? floatval( $data['price'] ) : 0;
        $duration    = isset( $data['duration'] ) ? intval( $data['duration'] ) : 0;
        $active      = ! empty( $data['active'] ) ? 1 : 0;

        if ( $name === '' ) {
            return false;
        }

        return $this->repo->create( array(
            'name'        => $name,
            'description' => $description,
            'price'       => $price,
            'duration'    => $duration,
            'active'      => $active,
        ) );
    }
}
