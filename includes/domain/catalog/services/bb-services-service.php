<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Capa de dominio para Services
 *
 * - Valida / sanea datos de formulario (admin)
 * - Habla con BB_Services_Repo
 * - Expone helpers para frontend (wizard)
 */
class BB_Services_Service {

    /** @var BB_Services_Repo */
    protected $repo;

    public function __construct( BB_Services_Repo $repo ) {
        $this->repo = $repo;
    }

    /**
     * Devuelve todos los servicios (activos o no), ordenados.
     *
     * @return array
     */
    public function get_all(): array {
        return $this->repo->get_all();
    }

    /**
     * Devuelve los servicios pensados para el wizard/frontend.
     *
     * De momento devolvemos TODO igual que get_all() y dejamos logs
     * para verificar que el wizard está recibiendo servicios.
     *
     * @return array
     */
    public function get_active_for_wizard(): array {
        $all = $this->repo->get_all();

        if ( ! is_array( $all ) ) {
            error_log( 'BB_Services_Service::get_active_for_wizard -> get_all() no devolvió array' );
            return array();
        }

        error_log( 'BB_Services_Service::get_active_for_wizard -> total servicios (sin filtrar) = ' . count( $all ) );

        // 🔥 Por ahora devolvemos todos, igual que el admin
        return $all;

        /*
        // Cuando veamos que el wizard ya muestra servicios,
        // podemos volver a filtrar sólo activos:
        $active = array_values(
            array_filter(
                $all,
                function ( $svc ) {
                    if ( ! isset( $svc['active'] ) ) {
                        return true;
                    }
                    return (int) $svc['active'] === 1;
                }
            )
        );

        error_log( 'BB_Services_Service::get_active_for_wizard -> activos = ' . count( $active ) );

        return $active;
        */
    }

    /**
     * Crea un servicio a partir de los datos del formulario (admin).
     *
     * @param array $data
     * @return bool  true si se insertó, false si hubo error/validación
     */
    public function create_from_form( array $data ): bool {

        $name        = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
        $price       = isset( $data['price'] ) ? floatval( $data['price'] ) : 0;
        $duration    = isset( $data['duration'] ) ? intval( $data['duration'] ) : 0; // en minutos
        $active      = ! empty( $data['active'] ) ? 1 : 0;

        // Validación mínima
        if ( $name === '' ) {
            return false;
        }

        // Mapeamos a las columnas reales de la tabla
        $payload = array(
            'name'             => $name,
            'description'      => $description,
            'base_price'       => $price,
            'duration_minutes' => $duration,
            'active'           => $active,
        );

        return $this->repo->insert( $payload );
    }

    /**
     * Actualiza un servicio existente desde datos de formulario (admin).
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function update_from_form( int $id, array $data ): bool {

        if ( $id <= 0 ) {
            return false;
        }

        $name        = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
        $price       = isset( $data['price'] ) ? floatval( $data['price'] ) : 0;
        $duration    = isset( $data['duration'] ) ? intval( $data['duration'] ) : 0; // en minutos
        $active      = ! empty( $data['active'] ) ? 1 : 0;

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

        return $this->repo->update( $id, $payload );
    }

    /**
     * Soft delete: marca active = 0
     *
     * @param int $id
     * @return bool
     */
    public function delete( int $id ): bool {
        return $this->repo->delete( $id );
    }

    /**
     * Devuelve un servicio por ID o null.
     *
     * @param int $id
     * @return array|null
     */
    public function get_by_id( int $id ): ?array {
        return $this->repo->get_by_id( $id );
    }
}
