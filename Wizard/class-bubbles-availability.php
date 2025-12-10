<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('Bubbles_Availability')) {

class Bubbles_Availability {

    /**
     * ¿Este día se puede reservar?
     *
     * - No permite hoy ni fechas pasadas.
     * - Solo permite días laborales (por defecto Lunes–Sábado).
     * - Más adelante aquí puedes chequear:
     *   - agenda de técnicos,
     *   - días bloqueados,
     *   - festivos, etc.
     */
    public static function is_date_bookable($date, $args = array()) {
        if (empty($date)) {
            return false;
        }

        $ts = strtotime($date);
        if (!$ts) {
            return false;
        }

        // Fecha actual en zona WP
        $today = current_time('Y-m-d');
        if ($date <= $today) {
            // No permitir hoy ni días pasados
            return false;
        }

        // Día de la semana: 1 (Lunes) ... 7 (Domingo)
        $weekday = (int) date('N', $ts);

        // Por defecto: se trabaja Lunes–Sábado (1–6), Domingo (7) cerrado
        $working_days = apply_filters('bubbles_working_days', array(1,2,3,4,5,6));
        if (!in_array($weekday, $working_days, true)) {
            return false;
        }

        // Aquí más adelante:
        // - chequear técnico específico
        // - días bloqueados, vacaciones, etc.

        return true;
    }

    /**
     * Slots disponibles para una fecha.
     * Si el día no es reservable, devolvemos array vacío.
     */
    public static function get_slots_for_date($date, $args = array()) {
        if (!self::is_date_bookable($date, $args)) {
            return array();
        }

        // Slots “dummy” por ahora
        $default_slots = array(
            '08:00 AM',
            '09:00 AM',
            '10:00 AM',
            '11:00 AM',
            '01:00 PM',
            '02:00 PM',
            '03:00 PM',
        );

        return apply_filters('bubbles_availability_slots', $default_slots, $date, $args);
    }
}
 /**
     * Días máximos de antelación para reservar.
     *
     * Hoy: 60 días (~2 meses).
     * Mañana: lo puedes cambiar desde el admin guardando una opción
     * o usando el filtro 'bubbles_max_advance_days'.
     */
    public static function get_max_advance_days() {
        // Ej: si algún día creas una página de opciones:
        $stored = get_option('bubbles_max_advance_days'); // opcional
        $days   = $stored !== false ? (int) $stored : 60;

        $days = (int) apply_filters('bubbles_max_advance_days', $days);

        if ($days < 1)   $days = 1;
        if ($days > 365) $days = 365; // seguridad

        return $days;
    }

    /** Primera fecha reservable (mañana) */
    public static function get_min_bookable_date() {
        $today = current_time('Y-m-d');
        $ts    = strtotime($today . ' +1 day');
        return date('Y-m-d', $ts);
    }

    /** Última fecha reservable (min + max_advance_days - 1) */
    public static function get_max_bookable_date() {
        $min  = self::get_min_bookable_date();
        $days = self::get_max_advance_days();
        $ts   = strtotime($min . ' +' . ($days - 1) . ' days');
        return date('Y-m-d', $ts);
    }

    /**
     * ¿Este día se puede reservar?
     * - Entre min y max
     * - Día laboral (por defecto Lunes–Sábado)
     */
    public static function is_date_bookable($date, $args = array()) {
        if (empty($date)) {
            return false;
        }

        $ts = strtotime($date);
        if (!$ts) {
            return false;
        }

        $min = self::get_min_bookable_date();
        $max = self::get_max_bookable_date();

        // Fuera de la ventana (antes de min o después de max)
        if ($date < $min || $date > $max) {
            return false;
        }

        // Día de la semana: 1 (Lunes) ... 7 (Domingo)
        $weekday = (int) date('N', $ts);

        // Por defecto: se trabaja Lunes–Sábado (1–6)
        $working_days = apply_filters('bubbles_working_days', array(1,2,3,4,5,6));
        if (!in_array($weekday, $working_days, true)) {
            return false;
        }

        // Aquí más adelante:
        // - chequear técnico concreto
        // - días bloqueados, etc.

        return true;
    }

    /**
     * Slots disponibles para una fecha.
     * Si el día no es reservable, devolvemos array vacío.
     */
    public static function get_slots_for_date($date, $args = array()) {
        if (!self::is_date_bookable($date, $args)) {
            return array();
        }

        $default_slots = array(
            '08:00 AM',
            '09:00 AM',
            '10:00 AM',
            '11:00 AM',
            '01:00 PM',
            '02:00 PM',
            '03:00 PM',
        );

        return apply_filters('bubbles_availability_slots', $default_slots, $date, $args);
    }
}
