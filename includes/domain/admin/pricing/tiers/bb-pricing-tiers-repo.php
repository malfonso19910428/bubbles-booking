<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Pricing_Tiers_Repo {

    /* =====================================================
     * Table
     * ===================================================== */

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'bb_pricing_tiers';
    }

    /* =====================================================
     * Schema
     * ===================================================== */

    public static function create_table(): void {
        global $wpdb;

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                slug VARCHAR(64) NOT NULL,
                label VARCHAR(191) NOT NULL,
                default_add DECIMAL(10,2) NOT NULL DEFAULT 0,
                sort_order INT(11) NOT NULL DEFAULT 10,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY idx_active_sort (is_active, sort_order)
            ) {$charset_collate};
        ";

        dbDelta( $sql );
        self::ensure_columns();
    }

    public static function ensure_columns(): void {
        global $wpdb;
        $table = self::table_name();

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SHOW TABLES LIKE %s", $table )
        );
        if ( ! $exists ) return;

        $col = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                'default_add'
            )
        );

        if ( ! $col ) {
            $wpdb->query(
                "ALTER TABLE {$table}
                 ADD COLUMN default_add DECIMAL(10,2) NOT NULL DEFAULT 0"
            );
        }
    }

    /* =====================================================
     * Reads
     * ===================================================== */

    public function list_all(): array {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    public function list_active(): array {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * ✅ Default add para un tier específico
     */
    public function get_default_add_by_id( int $tier_id ): float {
        global $wpdb;
        $table = self::table_name();

        if ( $tier_id <= 0 ) return 0.0;

        $val = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT default_add
                 FROM {$table}
                 WHERE id = %d AND is_active = 1
                 LIMIT 1",
                $tier_id
            )
        );

        $val = (float) ($val ?? 0);
        if ( $val < 0 ) $val = 0;

        return round($val, 2);
    }

    /**
     * ✅ Mapa [tier_id => default_add]
     */
    public function get_default_add_map(): array {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            "SELECT id, default_add
             FROM {$table}
             WHERE is_active = 1",
            ARRAY_A
        );

        $map = array();

        if ( is_array($rows) ) {
            foreach ( $rows as $r ) {
                $id  = (int) ($r['id'] ?? 0);
                $add = (float) ($r['default_add'] ?? 0);

                if ( $id > 0 ) {
                    if ( $add < 0 ) $add = 0;
                    $map[$id] = round($add, 2);
                }
            }
        }

        return $map;
    }

    /* =====================================================
     * Writes
     * ===================================================== */

    public function upsert_many( array $tiers ): void {
        global $wpdb;
        $table = self::table_name();

        foreach ( $tiers as $t ) {
            if ( ! is_array($t) ) continue;

            $id          = isset($t['id']) ? (int) $t['id'] : 0;
            $slug        = (string) ($t['slug'] ?? '');
            $label       = (string) ($t['label'] ?? '');
            $default_add = (float) ($t['default_add'] ?? 0);
            $sort        = (int)   ($t['sort_order'] ?? 10);
            $is_active   = (int)   ($t['is_active'] ?? 1);

            if ( $slug === '' || $label === '' ) continue;

            if ( $default_add < 0 ) $default_add = 0;
            $default_add = round($default_add, 2);

            $data = array(
                'slug'        => $slug,
                'label'       => $label,
                'default_add' => $default_add,
                'sort_order'  => $sort,
                'is_active'   => $is_active,
                'updated_at'  => current_time('mysql'),
            );

            $formats = array('%s','%s','%f','%d','%d','%s');

            if ( $id > 0 ) {
                $wpdb->update(
                    $table,
                    $data,
                    array('id' => $id),
                    $formats,
                    array('%d')
                );
            } else {

                $existing_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$table}
                         WHERE slug = %s LIMIT 1",
                        $slug
                    )
                );

                if ( $existing_id > 0 ) {
                    $wpdb->update(
                        $table,
                        $data,
                        array('id' => $existing_id),
                        $formats,
                        array('%d')
                    );
                } else {
                    unset($data['updated_at']);
                    $wpdb->insert(
                        $table,
                        $data,
                        array('%s','%s','%f','%d','%d')
                    );
                }
            }
        }
    }

    /* =====================================================
     * Seed
     * ===================================================== */

    public static function seed_defaults(): void {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            array('slug'=>'small',  'label'=>'Small Job',       'default_add'=>0, 'sort_order'=>10),
            array('slug'=>'medium', 'label'=>'Medium Job',      'default_add'=>0, 'sort_order'=>20),
            array('slug'=>'large',  'label'=>'Large Job',       'default_add'=>0, 'sort_order'=>30),
            array('slug'=>'xl',     'label'=>'Extra Large Job', 'default_add'=>0, 'sort_order'=>40),
        );

        foreach ( $defaults as $d ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                     WHERE slug = %s LIMIT 1",
                    $d['slug']
                )
            );
            if ( $exists ) continue;

            $wpdb->insert(
                $table,
                array(
                    'slug'        => $d['slug'],
                    'label'       => $d['label'],
                    'default_add' => (float) $d['default_add'],
                    'sort_order'  => (int) $d['sort_order'],
                    'is_active'   => 1,
                ),
                array('%s','%s','%f','%d','%d')
            );
        }
    }
}
