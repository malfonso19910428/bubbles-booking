<?php
if ( ! defined('ABSPATH') ) exit;

class BB_Vehicle_Type_Tiers_Repo {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bb_vehicle_type_tiers';
    }

    public static function create_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'bb_vehicle_type_tiers';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                vehicle_type VARCHAR(50) NOT NULL,
                tier_id BIGINT(20) UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT(11) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY (id),
                UNIQUE KEY vehicle_type (vehicle_type),
                KEY tier_id (tier_id),
                KEY is_active (is_active),
                KEY sort_order (sort_order)
            ) {$charset_collate};
        ";

        dbDelta($sql);
    }

    public function list_all(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, vehicle_type, tier_id, is_active, sort_order
             FROM {$this->table}
             ORDER BY is_active DESC, sort_order ASC, vehicle_type ASC",
            ARRAY_A
        );

        if ( ! is_array($rows) ) return array();

        return array_map(function($r){
            return array(
                'id'          => (int)($r['id'] ?? 0),
                'vehicle_type'=> (string)($r['vehicle_type'] ?? ''),
                'tier_id'     => (int)($r['tier_id'] ?? 0),
                'is_active'   => (int)($r['is_active'] ?? 1),
                'sort_order'  => (int)($r['sort_order'] ?? 0),
            );
        }, $rows);
    }

    public function get_tier_id(string $vehicle_type): int {
        global $wpdb;

        $vehicle_type = sanitize_key($vehicle_type);

        $tier_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT tier_id
                 FROM {$this->table}
                 WHERE vehicle_type = %s AND is_active = 1
                  LIMIT 1",
                $vehicle_type
            )
        );

        return (int)$tier_id;
    }

    /**
     * Upsert por vehicle_type (UNIQUE)
     */
    public function upsert(string $vehicle_type, int $tier_id, int $is_active = 1, int $sort_order = 0): bool {
        global $wpdb;

        $vehicle_type = sanitize_key($vehicle_type);
        if ($vehicle_type === '' || $tier_id <= 0) return false;

        $now = current_time('mysql');

        // si existe, update; si no, insert
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE vehicle_type = %s",
                $vehicle_type
            )
        );

        if ($existing_id > 0) {
            $updated = $wpdb->update(
                $this->table,
                array(
                    'tier_id'    => $tier_id,
                    'is_active'  => $is_active ? 1 : 0,
                    'sort_order' => $sort_order,
                    'updated_at' => $now,
                ),
                array('id' => $existing_id),
                array('%d','%d','%d','%s'),
                array('%d')
            );
            return $updated !== false;
        }

        $inserted = $wpdb->insert(
            $this->table,
            array(
                'vehicle_type' => $vehicle_type,
                'tier_id'      => $tier_id,
                'is_active'    => $is_active ? 1 : 0,
                'sort_order'   => $sort_order,
                'created_at'   => $now,
                'updated_at'   => $now,
            ),
            array('%s','%d','%d','%d','%s','%s')
        );

        return $inserted !== false;
    }
}
