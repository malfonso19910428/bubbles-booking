<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class BB_Draft_Steps_Repo {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bb_booking_drafts';
    }

    public function create_table_if_needed(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            current_step VARCHAR(40) NOT NULL DEFAULT 'vehicle',
            state LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            updated_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY status (status),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    public function get_by_token( string $token ): array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE token = %s LIMIT 1",
                $token
            ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : array();
    }

    /**
     * Devuelve el state como array (decodificado), o [].
     */
    public function get_state_by_token( string $token ): array {
        $row = $this->get_by_token( $token );
        if ( empty( $row ) ) return array();

        $raw = $row['state'] ?? '';
        if ( ! is_string( $raw ) || $raw === '' ) return array();

        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    /**
     * Upsert con MERGE seguro:
     * - Si existe el draft, fusiona state existente + state nuevo (array_replace_recursive)
     * - Si no existe, crea el draft con el state recibido
     *
     * IMPORTANT: Esto evita que al guardar package se "pierda" vehicle.
     */
    public function upsert( string $token, string $current_step, array $state, string $status = 'draft' ): bool {
        global $wpdb;

        $now = current_time( 'mysql' );

        // existe?
        $existing = $this->get_by_token( $token );

        if ( ! empty( $existing ) ) {

            // ✅ MERGE: state viejo + state nuevo
            $old_state = array();
            if ( ! empty( $existing['state'] ) && is_string( $existing['state'] ) ) {
                $tmp = json_decode( $existing['state'], true );
                if ( is_array( $tmp ) ) $old_state = $tmp;
            }

            // new gana sobre old en keys concretas, pero no borra lo demás
            $merged = array_replace_recursive( (array) $old_state, (array) $state );

            $res = $wpdb->update(
                $this->table,
                array(
                    'current_step' => $current_step,
                    'state'        => wp_json_encode( $merged ),
                    'status'       => $status,
                    'updated_at'   => $now,
                ),
                array( 'token' => $token ),
                array( '%s','%s','%s','%s' ),
                array( '%s' )
            );

            return ( $res !== false );
        }

        // INSERT (primer draft con este token)
        $res = $wpdb->insert(
            $this->table,
            array(
                'token'        => $token,
                'current_step' => $current_step,
                'state'        => wp_json_encode( $state ),
                'status'       => $status,
                'updated_at'   => $now,
                'created_at'   => $now,
            ),
            array( '%s','%s','%s','%s','%s','%s' )
        );

        return ( $res !== false );
    }

    public function delete_by_token( string $token ): bool {
        global $wpdb;

        $res = $wpdb->delete(
            $this->table,
            array( 'token' => $token ),
            array( '%s' )
        );

        return ( $res !== false );
    }
}
