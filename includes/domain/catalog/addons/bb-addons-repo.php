<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Addons_Repo {

    /** @var wpdb */
    protected $db;

    /** @var string */
    protected $table_name;

    public function __construct( wpdb $db = null ) {
        global $wpdb;
        $this->db         = $db ?: $wpdb;
        $this->table_name = $this->db->prefix . 'bb_addons';

        // Asegurar que la tabla exista
        $this->maybe_create_table();
    }

    /**
     * Crea la tabla de add-ons si no existe
     */
    protected function maybe_create_table() {

        // ¿Ya existe la tabla?
        $table_exists = $this->db->get_var(
            $this->db->prepare(
                "SHOW TABLES LIKE %s",
                $this->table_name
            )
        );

        if ( $table_exists === $this->table_name ) {
            return; // nada que hacer
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->db->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            description text NULL,
            price decimal(10,2) NOT NULL DEFAULT 0,
            duration int(11) NOT NULL DEFAULT 0,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Devuelve todos los add-ons
     *
     * @return array
     */
    public function get_all() {
        $sql  = "SELECT * FROM {$this->table_name} ORDER BY id DESC";
        $rows = $this->db->get_results( $sql, ARRAY_A );

        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Crea un nuevo add-on
     *
     * @param array $data
     * @return int|false ID insertado o false
     */
    public function create( array $data ) {

        $defaults = array(
            'name'        => '',
            'description' => '',
            'price'       => 0,
            'duration'    => 0,   // en minutos
            'active'      => 1,
        );

        $data = wp_parse_args( $data, $defaults );

        $inserted = $this->db->insert(
            $this->table_name,
            array(
                'name'        => $data['name'],
                'description' => $data['description'],
                'price'       => $data['price'],
                'duration'    => $data['duration'],
                'active'      => $data['active'],
            ),
            array( '%s', '%s', '%f', '%d', '%d' )
        );

        if ( ! $inserted ) {
            return false;
        }

        return (int) $this->db->insert_id;
    }

    // Más adelante puedes añadir: update(), delete(), get_by_id(), etc.
}
