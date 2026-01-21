<?php
/*
Plugin Name: Bubbles Booking
Description: Shortcodes y funciones para el proceso de booking de car detailing.
Version: 1.0.7
Author: Mily
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Constantes
define( 'BB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
require_once BB_PLUGIN_DIR . 'includes/integrations/helpers.php';
require_once BB_PLUGIN_DIR . 'includes/integrations/stripe/stripe-ajax.php';

// ===== Core (roles/permisos opcional) =====
$bb_roles_file = BB_PLUGIN_DIR . 'includes/core/class-bubbles-roles.php';
if ( file_exists( $bb_roles_file ) ) require_once $bb_roles_file;

$bb_media_permissions_file = BB_PLUGIN_DIR . 'includes/core/bb-media-permissions.php';
if ( file_exists( $bb_media_permissions_file ) ) require_once $bb_media_permissions_file;

// Assets
require_once BB_PLUGIN_DIR . 'includes/core/class-bb-assets.php';
BB_Assets::init();

// Installer (tablas/roles)
require_once BB_PLUGIN_DIR . 'includes/config/Installer.php';
register_activation_hook( __FILE__, array( 'Bubbles_Installer', 'activate' ) );



// ===== Tech module =====
add_action( 'plugins_loaded', function () {
    $bb_tech_module_file = BB_PLUGIN_DIR . 'includes/UI/tech/class-bb-tech-module.php';
    if ( file_exists( $bb_tech_module_file ) ) {
        require_once $bb_tech_module_file;

        if ( class_exists( 'BB_Tech_Module' ) ) {
            new BB_Tech_Module();
        }
    }
});

// ===== Wizard =====
$wizard_inc = BB_PLUGIN_DIR . 'includes/UI/Wizard/bubbles-wizard.php';
if ( file_exists( $wizard_inc ) ) {
    require_once $wizard_inc;

    // Si tu wizard registra el shortcode en __construct, instáncialo aquí:
    if ( class_exists( 'Bubbles_Wizard' ) ) {
        // Evitar doble instancia
        if ( empty( $GLOBALS['bubbles_wizard_instance'] ) ) {
            $GLOBALS['bubbles_wizard_instance'] = new Bubbles_Wizard();
        }
    }
}


// ===== Admin module =====
if ( is_admin() ) {
    $bb_admin_module_file = BB_PLUGIN_DIR . 'includes/UI/Admin/bb-admin-module.php';
    if ( file_exists( $bb_admin_module_file ) ) {
        require_once $bb_admin_module_file;
        if ( class_exists( 'BB_Admin_Module' ) ) {
            new BB_Admin_Module();
        }
    }
}
