<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Capa de dominio para Services
 *
 * - Valida / sanea datos de formulario
 * - Habla con BB_Services_Repo
 */
class BB_Services_Service {

    /** @var BB_Services_Repo */
    protected $repo;

    public function __construct( BB_Services_Repo $repo ) {
        $this->repo = $repo;
    }

    /**
     * Devuelve todos los servicios (para el admin)
     *
     * @return array
     */
    public function get_all() {
        return $this->repo->get_all();
    }

    /**
     * Crea un servicio a partir de los datos del formulario
     *
     * @param array $data
     * @return int|false  ID insertado o false
     */
    public function create_from_form( array $data ) {

        $name        = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
        $price       = isset( $data['price'] ) ? floatval( $data['price'] ) : 0;
        $duration    = isset( $data['duration'] ) ? intval( $data['duration'] ) : 0;
        $active      = ! empty( $data['active'] ) ? 1 : 0;

        // Validación mínima
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
