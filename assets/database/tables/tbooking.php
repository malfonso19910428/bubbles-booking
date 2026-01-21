<?php
if (!defined('ABSPATH')) exit;

class Bubbles_DB_TBooking {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'bubbles_tbooking';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
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
}
