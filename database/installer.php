<?php
if (!defined('ABSPATH')) exit;

class Bubbles_DB_Installer {

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Crear tablas principales
       require_once plugin_dir_path(__FILE__) . 'tables/tbooking.php';
        Bubbles_DB_TBooking::create_table();


        // Futuro:
        // require_once plugin_dir_path(__FILE__) . 'tables/services.php';
        // Bubbles_DB_Services::create_table();

          update_option('bubbles_booking_db_version', '1.0.0');
    }
}
