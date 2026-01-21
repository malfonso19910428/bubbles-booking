<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Repo: Global Availability Rules
 *
 * Table: {$wpdb->prefix}bb_global_availability_rules
 * Columns:
 *  - id (BIGINT, PK)
 *  - rule_key (VARCHAR) UNIQUE
 *  - rule_value (LONGTEXT)  // scalar o JSON
 *  - created_at (DATETIME)
 *  - updated_at (DATETIME)
 */
class BB_Availability_Rules_Repo {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bb_global_availability_rules';
    }

    /**
     * Create table (installer)
     */
    public static function create_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'bb_global_availability_rules';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
        CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_key VARCHAR(80) NOT NULL,
            rule_value LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY rule_key (rule_key)
        ) {$charset_collate};
        ";

        dbDelta( $sql );
    }

    /**
     * Get a single rule value (raw string). Returns null if not found.
     */
    public function get_rule( string $key ): ?string {
        global $wpdb;

        $key = sanitize_key( $key );

        $sql = $wpdb->prepare(
            "SELECT rule_value FROM {$this->table} WHERE rule_key = %s LIMIT 1",
            $key
        );

        $value = $wpdb->get_var( $sql );

        if ( $value === null ) {
            return null;
        }

        // DB can return '' (empty string) meaning "set but empty"
        return (string) $value;
    }

    /**
     * Get all rules as [key => raw_value_string]
     */
    public function get_all(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT rule_key, rule_value FROM {$this->table}",
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return array();
        }

        $out = array();
        foreach ( $rows as $row ) {
            $k = isset( $row['rule_key'] ) ? (string) $row['rule_key'] : '';
            if ( $k === '' ) continue;

            $out[ $k ] = isset( $row['rule_value'] ) ? (string) $row['rule_value'] : '';
        }

        return $out;
    }

    /**
     * Save rule (insert or update).
     * Accepts scalar/array/object; arrays/objects are stored as JSON.
     */
    public function save_rule( string $key, $value ): bool {
        global $wpdb;

        $key = sanitize_key( $key );
        if ( $key === '' ) {
            return false;
        }

        $stored_value = $this->normalize_value_for_storage( $value );

        // Check if exists
        $existing = $this->get_rule( $key );

        $data = array(
            'rule_key'   => $key,
            'rule_value' => $stored_value,
        );

        $formats = array( '%s', '%s' );

        if ( $existing === null ) {
            $result = $wpdb->insert( $this->table, $data, $formats );
            return ( $result !== false );
        }

        $result = $wpdb->update(
            $this->table,
            array( 'rule_value' => $stored_value ),
            array( 'rule_key' => $key ),
            array( '%s' ),
            array( '%s' )
        );

        return ( $result !== false );
    }

    /**
     * Delete rule by key
     */
    public function delete_rule( string $key ): bool {
        global $wpdb;

        $key = sanitize_key( $key );
        if ( $key === '' ) {
            return false;
        }

        $result = $wpdb->delete(
            $this->table,
            array( 'rule_key' => $key ),
            array( '%s' )
        );

        return ( $result !== false );
    }

    /**
     * Helpers
     */

    private function normalize_value_for_storage( $value ): string {
        // arrays/objects → JSON
        if ( is_array( $value ) || is_object( $value ) ) {
            $json = wp_json_encode( $value );
            return ( $json !== false ) ? $json : '';
        }

        // bool
        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        // null
        if ( $value === null ) {
            return '';
        }

        // numbers/strings
        return (string) $value;
    }
}
