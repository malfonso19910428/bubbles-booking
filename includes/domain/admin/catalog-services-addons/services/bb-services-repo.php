<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Services_Repo {

    /** @var string */
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bb_services';
    }

    /**
     * Crea la tabla de servicios en la BD (llamado desde Bubbles_Installer::activate)
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'bb_services';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
            CREATE TABLE {$table_name} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL,
                description TEXT NULL,
                base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                duration_minutes INT(11) UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT(11) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                KEY active (active),
                KEY sort_order (sort_order)
            ) {$charset_collate};
        ";

        dbDelta( $sql );
    }

    /**
     * Inserta un nuevo servicio
     */
    public function insert( array $data ): bool {
        global $wpdb;

        $now = current_time( 'mysql' );

        $inserted = $wpdb->insert(
            $this->table_name,
            array(
                'name'             => $data['name']             ?? '',
                'description'      => $data['description']      ?? '',
                'base_price'       => $data['base_price']       ?? 0,
                'duration_minutes' => $data['duration_minutes'] ?? 0,
                'active'           => isset( $data['active'] ) ? (int) $data['active'] : 1,
                'sort_order'       => $data['sort_order']       ?? 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ),
            array(
                '%s', // name
                '%s', // description
                '%f', // base_price
                '%d', // duration_minutes
                '%d', // active
                '%d', // sort_order
                '%s', // created_at
                '%s', // updated_at
            )
        );

        return $inserted !== false;
    }

    /**
     * Actualiza un servicio existente
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        if ( $id <= 0 ) {
            return false;
        }

        $now = current_time( 'mysql' );

        $fields = array(
            'name'             => $data['name']             ?? '',
            'description'      => $data['description']      ?? '',
            'base_price'       => $data['base_price']       ?? 0,
            'duration_minutes' => $data['duration_minutes'] ?? 0,
            'active'           => isset( $data['active'] ) ? (int) $data['active'] : 1,
            'sort_order'       => $data['sort_order']       ?? 0,
            'updated_at'       => $now,
        );

        $formats = array(
            '%s', // name
            '%s', // description
            '%f', // base_price
            '%d', // duration_minutes
            '%d', // active
            '%d', // sort_order
            '%s', // updated_at
        );

        $updated = $wpdb->update(
            $this->table_name,
            $fields,
            array( 'id' => $id ),
            $formats,
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Soft delete: marca active = 0
     */
    public function delete( int $id ): bool {
        global $wpdb;

        if ( $id <= 0 ) {
            return false;
        }

        $now = current_time( 'mysql' );

        $updated = $wpdb->update(
            $this->table_name,
            array(
                'active'     => 0,
                'updated_at' => $now,
            ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Devuelve todos los servicios (activos o no), ordenados.
     *
     * Nota: Devuelve base_price como campo principal.
     * Incluye price como alias temporal para compatibilidad.
     */
    public function get_all(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, name, description, base_price, duration_minutes, active, sort_order
             FROM {$this->table_name}
             ORDER BY active DESC, sort_order ASC, name ASC",
            ARRAY_A
        );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return array();
        }

        return array_map( function ( $row ) {

            $base = isset( $row['base_price'] ) ? (float) $row['base_price'] : 0.0;

            return array(
                'id'               => (int) ( $row['id'] ?? 0 ),
                'name'             => (string) ( $row['name'] ?? '' ),
                'description'      => (string) ( $row['description'] ?? '' ),

                // ✅ campo correcto
                'base_price'       => $base,

                // ⚠️ alias temporal (quítalo cuando todo use base_price)
                'price'            => $base,

                'duration_minutes' => isset( $row['duration_minutes'] ) ? (int) $row['duration_minutes'] : 0,
                'active'           => isset( $row['active'] ) ? (int) $row['active'] : 1,
                'sort_order'       => isset( $row['sort_order'] ) ? (int) $row['sort_order'] : 0,
            );
        }, $rows );
    }

    /**
     * Obtiene un servicio por ID (aunque esté inactivo)
     *
     * Nota: Devuelve base_price como campo principal.
     * Incluye price como alias temporal para compatibilidad.
     */
    public function get_by_id( int $id ): ?array {
        global $wpdb;

        if ( $id <= 0 ) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, description, base_price, duration_minutes, active, sort_order
                 FROM {$this->table_name}
                 WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $base = isset( $row['base_price'] ) ? (float) $row['base_price'] : 0.0;

        return array(
            'id'               => (int) ( $row['id'] ?? 0 ),
            'name'             => (string) ( $row['name'] ?? '' ),
            'description'      => (string) ( $row['description'] ?? '' ),

            // ✅ campo correcto
            'base_price'       => $base,

            // ⚠️ alias temporal
            'price'            => $base,

            'duration_minutes' => isset( $row['duration_minutes'] ) ? (int) $row['duration_minutes'] : 0,
            'active'           => isset( $row['active'] ) ? (int) $row['active'] : 1,
            'sort_order'       => isset( $row['sort_order'] ) ? (int) $row['sort_order'] : 0,
        );
    }
}
