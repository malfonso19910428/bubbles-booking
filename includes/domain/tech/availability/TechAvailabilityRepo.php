<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TechAvailabilityRepo {

    /**
     * @var string
     */
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tTechAvailability';
    }

    /**
     * Crea la tabla de disponibilidad de técnicos
     */
    public static function create_table() {
        global $wpdb;

        $table   = $wpdb->prefix . 'tTechAvailability';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tech_id BIGINT UNSIGNED NOT NULL,
            weekday TINYINT(1) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY tech_weekday (tech_id, weekday)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Devuelve todos los slots de la semana para un técnico,
     * como una lista PLANA de filas.
     *
     * Estructura:
     * [
     *   [
     *     'id'          => ...,
     *     'tech_id'     => ...,
     *     'weekday'     => 1,
     *     'start_time'  => '09:00:00',
     *     'end_time'    => '17:00:00',
     *     'is_available'=> 1,
     *     'created_at'  => ...,
     *     'updated_at'  => ...,
     *   ],
     *   ...
     * ]
     *
     * El agrupado por día lo hace TechAvailabilityService::week_for_user()
     *
     * @param int $tech_id
     * @return array
     */
    public function get_week( $tech_id ) {
        global $wpdb;

        if ( ! $tech_id ) {
            return array();
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * 
                 FROM {$this->table}
                 WHERE tech_id = %d
                 ORDER BY weekday, start_time",
                $tech_id
            ),
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Reemplaza toda la semana de un técnico:
     * - Borra todos los registros actuales del técnico
     * - Inserta los nuevos slots
     *
     * @param int   $tech_id
     * @param array $slots
     */
    public function replace_week( $tech_id, $slots ) {
        global $wpdb;

        if ( ! $tech_id ) {
            return;
        }

        // Borramos toda la disponibilidad anterior de este técnico
        $wpdb->delete(
            $this->table,
            array( 'tech_id' => $tech_id ),
            array( '%d' )
        );

        if ( empty( $slots ) || ! is_array( $slots ) ) {
            return; // Nada que insertar, semana vacía = sin disponibilidad
        }

        $now = current_time( 'mysql' );

        foreach ( $slots as $s ) {

            // Validación defensiva
            if ( ! isset( $s['weekday'], $s['start_time'], $s['end_time'] ) ) {
                continue;
            }

            $weekday    = (int) $s['weekday'];
            $start_time = $s['start_time'];
            $end_time   = $s['end_time'];

            $wpdb->insert(
                $this->table,
                array(
                    'tech_id'      => $tech_id,
                    'weekday'      => $weekday,
                    'start_time'   => $start_time,
                    'end_time'     => $end_time,
                    'is_available' => 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ),
                array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
            );
        }
    }
}
