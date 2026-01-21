<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Job_Targets_Repo {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bb_job_targets';
    }

    public static function create_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'bb_job_targets';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
        CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            industry VARCHAR(50) NOT NULL DEFAULT 'auto',
            slug VARCHAR(80) NOT NULL,
            label VARCHAR(120) NOT NULL,

            -- ✅ SERIO: referencia al tier real (wp_bb_pricing_tiers.id)
            tier_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,

            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT(11) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY industry_slug (industry, slug),
            KEY industry (industry),
            KEY tier_id (tier_id),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) {$charset_collate};
        ";

        dbDelta( $sql );
    }

    public function table_exists(): bool {
        global $wpdb;
        $t = $this->table;
        $found = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $t ) );
        return ( $found === $t );
    }

    public function count_all(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
    }

    public function get_all( string $industry = '' ): array {
        global $wpdb;

        if ( $industry !== '' ) {
            $industry = sanitize_key( $industry );
            return (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE industry = %s ORDER BY sort_order ASC, label ASC",
                    $industry
                ),
                ARRAY_A
            );
        }

        return (array) $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY industry ASC, sort_order ASC, label ASC",
            ARRAY_A
        );
    }

    public function get_active_all( string $industry = '' ): array {
        global $wpdb;

        if ( $industry !== '' ) {
            $industry = sanitize_key( $industry );
            return (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE industry = %s AND is_active = 1 ORDER BY sort_order ASC, label ASC",
                    $industry
                ),
                ARRAY_A
            );
        }

        return (array) $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY industry ASC, sort_order ASC, label ASC",
            ARRAY_A
        );
    }

    public function get_by_id( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    public function get_by_industry_slug( string $industry, string $slug ): ?array {
        global $wpdb;
        $industry = sanitize_key($industry);
        $slug     = sanitize_title($slug);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE industry = %s AND slug = %s LIMIT 1", $industry, $slug),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * ✅ Trae el job target con el tier real (incluye default_add)
     */
    public function get_with_tier( int $id ): ?array {
        global $wpdb;
        $tiers_table = $wpdb->prefix . 'bb_pricing_tiers';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT jt.*, t.slug AS tier_slug, t.label AS tier_label, t.default_add
                 FROM {$this->table} jt
                 INNER JOIN {$tiers_table} t ON t.id = jt.tier_id
                 WHERE jt.id = %d
                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function upsert( array $data ): int {
        global $wpdb;

        $id       = isset($data['id']) ? (int) $data['id'] : 0;
        $industry = isset($data['industry']) ? sanitize_key($data['industry']) : 'auto';

        $label = isset($data['label']) ? sanitize_text_field($data['label']) : '';

        // ✅ slug automático desde label (si no viene)
        $slug = isset($data['slug']) ? sanitize_title( (string) $data['slug'] ) : '';
        if ( $slug === '' ) {
            $slug = sanitize_title( $label );
        }

        $tier_id = isset($data['tier_id']) ? (int) $data['tier_id'] : 0;

        $desc       = isset($data['description']) ? sanitize_textarea_field($data['description']) : '';
        $is_active  = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        $sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        if ( $slug === '' || $label === '' || $tier_id <= 0 ) {
            return 0;
        }

        // ✅ Validar que el tier existe y está activo
        if ( ! $this->tier_exists_active( $tier_id ) ) {
            return 0;
        }

        // ✅ Evitar duplicados por (industry, slug) cuando es nuevo o cuando cambia el slug
        if ( $this->slug_exists( $industry, $slug, $id ) ) {
            return 0;
        }

        $now = current_time('mysql');

        if ( $id > 0 ) {
            $wpdb->update(
                $this->table,
                array(
                    'industry'     => $industry,
                    'slug'         => $slug,
                    'label'        => $label,
                    'tier_id'      => $tier_id,
                    'description'  => $desc,
                    'is_active'    => $is_active,
                    'sort_order'   => $sort_order,
                    'updated_at'   => $now,
                ),
                array('id' => $id),
                array('%s','%s','%s','%d','%s','%d','%d','%s'),
                array('%d')
            );
            return $id;
        }

        // sort_order auto si viene 0
        if ( $sort_order <= 0 ) {
            $sort_order = $this->get_next_sort_order( $industry );
        }

        $wpdb->insert(
            $this->table,
            array(
                'industry'     => $industry,
                'slug'         => $slug,
                'label'        => $label,
                'tier_id'      => $tier_id,
                'description'  => $desc,
                'is_active'    => $is_active,
                'sort_order'   => $sort_order,
                'created_at'   => $now,
                'updated_at'   => $now,
            ),
            array('%s','%s','%s','%d','%s','%d','%d','%s','%s')
        );

        return (int) $wpdb->insert_id;
    }

    public function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table, array('id' => $id), array('%d') );
    }

    public function slug_exists( string $industry, string $slug, int $exclude_id = 0 ): bool {
        global $wpdb;
        $industry = sanitize_key($industry);
        $slug     = sanitize_title($slug);

        if ( $exclude_id > 0 ) {
            $found = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$this->table} WHERE industry = %s AND slug = %s AND id <> %d LIMIT 1",
                    $industry, $slug, $exclude_id
                )
            );
        } else {
            $found = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$this->table} WHERE industry = %s AND slug = %s LIMIT 1",
                    $industry, $slug
                )
            );
        }

        return ! empty($found);
    }

    public function get_next_sort_order( string $industry ): int {
        global $wpdb;
        $industry = sanitize_key($industry);

        $max = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(sort_order) FROM {$this->table} WHERE industry = %s",
                $industry
            )
        );

        // saltos de 10 para que reordenar sea fácil
        return $max > 0 ? ($max + 10) : 10;
    }

    /**
     * ✅ Update rápido para inline edit (sort_order, tier_id, is_active)
     */
    public function update_fields( int $id, array $fields ): bool {
        global $wpdb;

        if ( $id <= 0 ) return false;

        $data = array();
        $fmt  = array();

        if ( array_key_exists('sort_order', $fields) ) {
            $data['sort_order'] = (int) $fields['sort_order'];
            $fmt[] = '%d';
        }

        if ( array_key_exists('tier_id', $fields) ) {
            $tier_id = (int) $fields['tier_id'];
            if ( $tier_id <= 0 || ! $this->tier_exists_active($tier_id) ) {
                return false;
            }
            $data['tier_id'] = $tier_id;
            $fmt[] = '%d';
        }

        if ( array_key_exists('is_active', $fields) ) {
            $data['is_active'] = (int) $fields['is_active'];
            $fmt[] = '%d';
        }

        if ( empty($data) ) return false;

        $data['updated_at'] = current_time('mysql');
        $fmt[] = '%s';

        $ok = $wpdb->update(
            $this->table,
            $data,
            array('id' => $id),
            $fmt,
            array('%d')
        );

        return $ok !== false;
    }

    /**
     * ✅ valida que el tier exista y esté activo en wp_bb_pricing_tiers
     */
    private function tier_exists_active( int $tier_id ): bool {
        global $wpdb;
        $tiers_table = $wpdb->prefix . 'bb_pricing_tiers';

        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$tiers_table} WHERE id = %d AND is_active = 1 LIMIT 1",
                $tier_id
            )
        );

        return ! empty($found);
    }
}
