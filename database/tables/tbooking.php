<?php
if (!defined('ABSPATH')) exit;

class Bubbles_DB_TBooking {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'bubbles_tbooking';
    }

    /** Crear/actualizar estructura de tabla */
    public static function create_table() {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,

            customer_name VARCHAR(200) NOT NULL,
            customer_phone VARCHAR(40) NOT NULL,
            customer_email VARCHAR(200) NOT NULL,

            vehicle_year VARCHAR(10) NULL,
            vehicle_make VARCHAR(60) NULL,
            vehicle_model VARCHAR(100) NULL,

            package_id VARCHAR(100) NULL,
            package_label VARCHAR(200) NULL,
            package_price DECIMAL(10,2) NOT NULL DEFAULT 0,

            addons_json LONGTEXT NULL,
            addons_total DECIMAL(10,2) NOT NULL DEFAULT 0,

            date DATE NULL,
            time VARCHAR(30) NULL,

            address TEXT NULL,
            address_type VARCHAR(20) NULL,
            address_extra VARCHAR(200) NULL,
            address_city VARCHAR(100) NULL,
            address_state VARCHAR(20) NULL,
            address_zip VARCHAR(20) NULL,
            address_lat DECIMAL(10,6) NULL,
            address_lng DECIMAL(10,6) NULL,

            notes TEXT NULL,

            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,

            order_id BIGINT(20) UNSIGNED NULL,
            payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
            payment_method VARCHAR(50) NULL,

            PRIMARY KEY (id),
            KEY idx_date (date),
            KEY idx_status (status),
            KEY idx_order (order_id)
        ) {$charset};";

        dbDelta($sql);
    }

    /** Insertar nueva reserva, devuelve ID o WP_Error */
    public static function insert($data) {
        global $wpdb;
        $table = self::table_name();

        $now = current_time('mysql');

        $defaults = array(
            'status'          => 'pending-payment',
            'created_at'      => $now,
            'updated_at'      => $now,

            'customer_name'   => '',
            'customer_phone'  => '',
            'customer_email'  => '',

            'vehicle_year'    => '',
            'vehicle_make'    => '',
            'vehicle_model'   => '',

            'package_id'      => '',
            'package_label'   => '',
            'package_price'   => 0,

            'addons'          => array(),
            'addons_total'    => 0,

            'date'            => null,
            'time'            => '',

            'address'         => '',
            'address_type'    => '',
            'address_extra'   => '',
            'address_city'    => '',
            'address_state'   => '',
            'address_zip'     => '',
            'address_lat'     => null,
            'address_lng'     => null,

            'notes'           => '',

            'total_amount'    => 0,

            'order_id'        => null,
            'payment_status'  => 'unpaid',
            'payment_method'  => '',
        );

        $data = wp_parse_args($data, $defaults);

        // addons_json
        $addons_json = '';
        if (!empty($data['addons']) && is_array($data['addons'])) {
            $addons_json = wp_json_encode($data['addons']);
        }

        $insert = array(
            'status'          => $data['status'],
            'created_at'      => $data['created_at'],
            'updated_at'      => $data['updated_at'],

            'customer_name'   => $data['customer_name'],
            'customer_phone'  => $data['customer_phone'],
            'customer_email'  => $data['customer_email'],

            'vehicle_year'    => $data['vehicle_year'],
            'vehicle_make'    => $data['vehicle_make'],
            'vehicle_model'   => $data['vehicle_model'],

            'package_id'      => $data['package_id'],
            'package_label'   => $data['package_label'],
            'package_price'   => $data['package_price'],

            'addons_json'     => $addons_json,
            'addons_total'    => $data['addons_total'],

            'date'            => $data['date'],
            'time'            => $data['time'],

            'address'         => $data['address'],
            'address_type'    => $data['address_type'],
            'address_extra'   => $data['address_extra'],
            'address_city'    => $data['address_city'],
            'address_state'   => $data['address_state'],
            'address_zip'     => $data['address_zip'],
            'address_lat'     => $data['address_lat'],
            'address_lng'     => $data['address_lng'],

            'notes'           => $data['notes'],

            'total_amount'    => $data['total_amount'],

            'order_id'        => $data['order_id'],
            'payment_status'  => $data['payment_status'],
            'payment_method'  => $data['payment_method'],
        );

        $formats = array(
            '%s','%s','%s',
            '%s','%s','%s',
            '%s','%s','%s',
            '%s','%f',
            '%s','%f',
            '%s','%s',
            '%s','%s','%s','%s','%s','%f','%f',
            '%s',
            '%f',
            '%d','%s','%s'
        );

        $ok = $wpdb->insert($table, $insert, $formats);
        if (!$ok) {
            return new WP_Error('db_insert_error', 'Could not insert tbooking record.');
        }

        return (int) $wpdb->insert_id;
    }

    /** Cambiar status de la reserva */
    public static function update_status($id, $status) {
        global $wpdb;
        $table = self::table_name();
        $id = (int)$id;
        if ($id <= 0) return false;

        return $wpdb->update(
            $table,
            array(
                'status'     => sanitize_text_field($status),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array('%s','%s'),
            array('%d')
        );
    }

    /** Enlazar reserva con pedido WooCommerce */
    public static function link_order($id, $order_id, $payment_status = null, $payment_method = null) {
        global $wpdb;
        $table = self::table_name();
        $id       = (int)$id;
        $order_id = (int)$order_id;
        if ($id <= 0 || $order_id <= 0) return false;

        $fields = array(
            'order_id'   => $order_id,
            'updated_at' => current_time('mysql'),
        );
        $formats = array('%d','%s');

        if ($payment_status !== null) {
            $fields['payment_status'] = sanitize_text_field($payment_status);
            $formats[] = '%s';
        }
        if ($payment_method !== null) {
            $fields['payment_method'] = sanitize_text_field($payment_method);
            $formats[] = '%s';
        }

        return $wpdb->update(
            $table,
            $fields,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }

    /** Obtener una reserva como array */
    public static function get($id) {
        global $wpdb;
        $table = self::table_name();
        $id = (int)$id;
        if ($id <= 0) return null;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        if (!$row) return null;

        $row['addons'] = array();
        if (!empty($row['addons_json'])) {
            $dec = json_decode($row['addons_json'], true);
            if (is_array($dec)) {
                $row['addons'] = $dec;
            }
        }

        return $row;
    }
}
