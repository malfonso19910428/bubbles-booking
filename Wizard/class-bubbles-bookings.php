<?php
// Salir si alguien intenta acceder directo
if (!defined('ABSPATH')) exit;

/**
 * Núcleo de reservas de Bubbles Booking
 *
 * Por ahora es solo la estructura básica.
 * Más adelante aquí centralizamos:
 *  - Crear reservas
 *  - Leer reservas
 *  - Buscar por fecha / cliente
 *  - (y luego) pasar de CPT a tablas personalizadas
 */

if (!class_exists('Bubbles_Bookings')) {

class Bubbles_Bookings {

    /**
     * Crear una nueva reserva.
     *
     * Hoy: aquí usaremos CPT y post_meta.
     * Mañana: aquí usaremos tablas personalizadas.
     *
     * @param array $data Datos de la reserva (cliente, vehículo, fecha, etc.)
     * @return int|WP_Error ID de la reserva o error.
     */
    public static function create($data = array()) {
                  // Valores por defecto
        $defaults = array(
            'customer_name'   => '',
            'customer_phone'  => '',
            'customer_email'  => '',
            'vehicle_year'    => '',
            'vehicle_make'    => '',
            'vehicle_model'   => '',
            'package'         => '',
            'date'            => '', // formato Y-m-d idealmente
            'time'            => '', // ej: "08:00 AM"
            'status'          => 'pending', // pending, confirmed, cancelled...
            'notes'           => '',
        );

        // Mezclamos defaults con lo que venga
        $data = wp_parse_args($data, $defaults);

        // Sanitizar datos básicos
        $data['customer_name']  = sanitize_text_field($data['customer_name']);
        $data['customer_phone'] = sanitize_text_field($data['customer_phone']);
        $data['customer_email'] = sanitize_email($data['customer_email']);
        $data['vehicle_year']   = sanitize_text_field($data['vehicle_year']);
        $data['vehicle_make']   = sanitize_text_field($data['vehicle_make']);
        $data['vehicle_model']  = sanitize_text_field($data['vehicle_model']);
        $data['package']        = sanitize_text_field($data['package']);
        $data['date']           = sanitize_text_field($data['date']);
        $data['time']           = sanitize_text_field($data['time']);
        $data['status']         = sanitize_text_field($data['status']);
        $data['notes']          = sanitize_textarea_field($data['notes']);

        // Construir un título bonito para el post de la reserva
        $title_parts = array();

        if (!empty($data['customer_name'])) {
            $title_parts[] = $data['customer_name'];
        }

        if (!empty($data['vehicle_make']) || !empty($data['vehicle_model'])) {
            $title_parts[] = trim($data['vehicle_make'].' '.$data['vehicle_model']);
        }

        if (!empty($data['date']) && !empty($data['time'])) {
            $title_parts[] = $data['date'].' '.$data['time'];
        }

        $post_title = implode(' – ', $title_parts);
        if (empty($post_title)) {
            $post_title = 'Bubbles booking';
        }

        // Preparar el post (usamos un CPT llamado "bb_booking")
        $postarr = array(
            'post_type'   => 'bb_booking',   // luego crearemos este CPT
            'post_status' => 'publish',      // o 'pending' si prefieres revisar
            'post_title'  => $post_title,
        );

        // Insertar el post
        $booking_id = wp_insert_post($postarr, true);

        if (is_wp_error($booking_id)) {
            return $booking_id; // devolvemos el error para manejarlo arriba
        }

        // Meta keys que queremos guardar
        $meta_keys = array(
            'customer_name',
            'customer_phone',
            'customer_email',
            'vehicle_year',
            'vehicle_make',
            'vehicle_model',
            'package',
            'date',
            'time',
            'status',
            'notes',
        );

        foreach ($meta_keys as $key) {
            update_post_meta($booking_id, '_bb_' . $key, $data[$key]);
        }

        return $booking_id;
    }

    

    /**
     * Obtener una reserva por ID.
     *
     * @param int $booking_id
     * @return array|null
     */
    public static function get($booking_id) {
        // TODO: implementar más adelante
        return null;
    }

    /**
     * Buscar reservas por filtros.
     *
     * @param array $args filtros (fecha, cliente, estado, etc.)
     * @return array lista de reservas básicas
     */
    public static function find($args = array()) {
        // TODO: implementar más adelante
        return array();
    }
}

}
