<?php
error_log('PLUGIN LOADED TEST: ' . __FILE__);
/*
Plugin Name: Bubbles Booking
Description: Shortcodes y funciones para el proceso de booking de car detailing.
Version: 1.0.7
Author: Mily
*/

// Seguridad básica: evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) exit;

// =====================================
// 1) Constantes de ruta y URL del plugin
// =====================================
define( 'BB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// =====================================
// 2) Includes de core (roles, assets) + INSTALLER
// =====================================

// Roles del plugin (bb_staff) – opcional, pero lo dejamos cargado
$bb_roles_file = BB_PLUGIN_DIR . 'includes/core/class-bubbles-roles.php';
if ( file_exists( $bb_roles_file ) ) {
    require_once $bb_roles_file;
}

$bb_media_permissions_file = BB_PLUGIN_DIR . 'includes/core/bb-media-permissions.php';
if ( file_exists( $bb_media_permissions_file ) ) {
    require_once $bb_media_permissions_file;
}

// Gestor centralizado de CSS/JS
require_once BB_PLUGIN_DIR . 'includes/core/class-bb-assets.php';
BB_Assets::init();

// Instalador del plugin (roles + tablas, etc.)
require_once BB_PLUGIN_DIR . 'includes/config/Installer.php';

// =====================================
// 3) Módulo técnico (staff dashboard)
// =====================================

$bb_tech_module_file = BB_PLUGIN_DIR . 'includes/UI/tech/class-bb-tech-module.php';
if ( file_exists( $bb_tech_module_file ) ) {
    require_once $bb_tech_module_file;
} else {
    error_log( 'Bubbles Booking: ❌ No se encontró ' . $bb_tech_module_file );
}

// (Opcional) clase de disponibilidad técnica antigua, si la tienes separada
$bb_tech_availability_file = BB_PLUGIN_DIR . 'includes/UI/tech/class-bubbles-tech-availability.php';
if ( file_exists( $bb_tech_availability_file ) ) {
    require_once $bb_tech_availability_file;
}

// =====================================
// 4) Hooks de activación / carga inicial
// =====================================

// Hook de activación: delega en el instalador
register_activation_hook(
    __FILE__,
    array( 'Bubbles_Installer', 'activate' )
);

// Instanciar módulos cuando los plugins estén cargados
add_action( 'plugins_loaded', function () {

    // Nuevo módulo técnico (maneja [bb_staff_dashboard])
    if ( class_exists( 'BB_Tech_Module' ) ) {
        new BB_Tech_Module();
    } else {
        error_log( 'Bubbles Booking: ❌ BB_Tech_Module no existe después de cargar el archivo.' );
    }

    // Disponibilidad técnica (clase vieja, si aún la usas)
    if ( class_exists( 'Bubbles_Tech_Availability' ) ) {
        new Bubbles_Tech_Availability();
    }
    // OJO: ya NO instanciamos Bubbles_Tech_Dashboard, eso lo hace BB_Tech_Module con el shortcode
} );

// =====================================
// 5) Silenciar post type legacy de WooCommerce (shop_order_placehold)
//    Evita notices de "post type not registered" en map_meta_cap
// =====================================

add_action( 'init', function () {
    if ( post_type_exists( 'shop_order_placehold' ) ) {
        return;
    }

    register_post_type(
        'shop_order_placehold',
        array(
            'labels' => array(
                'name'          => 'Legacy Orders',
                'singular_name' => 'Legacy Order',
            ),
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'map_meta_cap'        => true,
        )
    );
}, 0 );

// =====================================
// 6) Includes de catálogo y core (Wizard)
// =====================================

// Catálogo de add-ons (ya movido a domain/catalog)
$addons_catalog = BB_PLUGIN_DIR . 'includes/domain/catalog/addons-catalog.php';
if ( file_exists( $addons_catalog ) ) {
    require_once $addons_catalog;
} else {
    error_log( 'Bubbles Booking: ❌ Falta includes/domain/catalog/addons-catalog.php' );
}

// Core: reservas y paquetes
$bookings_file = BB_PLUGIN_DIR . 'includes/UI/Wizard/class-bubbles-bookings.php';
if ( file_exists( $bookings_file ) ) {
    require_once $bookings_file;
}

$packages_file = BB_PLUGIN_DIR . 'includes/UI/Wizard/class-bubbles-packages.php';
if ( file_exists( $packages_file ) ) {
    require_once $packages_file;
}

// Core: controlador de confirmación y pagos (Stripe + booking)
$confirm_checkout = BB_PLUGIN_DIR . 'includes/UI/Wizard/class-bubbles-confirm-checkout.php';
if ( file_exists( $confirm_checkout ) ) {
    require_once $confirm_checkout;
}

// =====================================
// 7) Includes principales del plugin (Wizard, precios, vehículos)
// =====================================

// Wizard principal (flujo de booking)
$wizard_inc = BB_PLUGIN_DIR . 'includes/UI/Wizard/bubbles-wizard.php';
if ( file_exists( $wizard_inc ) ) {
    require_once $wizard_inc;
}

// Módulo de precios (para el botón "See my price")
$pricing_inc = BB_PLUGIN_DIR . 'includes/UI/Wizard/bubbles-custom-price.php';
if ( file_exists( $pricing_inc ) ) {
    require_once $pricing_inc;
} else {
    // Aviso en el admin si falta el módulo de precios
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Bubbles Booking:</strong> Falta <code>includes/UI/Wizard/bubbles-custom-price.php</code>.</p></div>';
    } );
}

// (Opcional) CPT + helpers si quieres guardar/listar/borrar vehículos.
$vehicles_cpt = BB_PLUGIN_DIR . 'includes/UI/Wizard/bubbles-vehicles-cpt.php';
if ( file_exists( $vehicles_cpt ) ) {
    require_once $vehicles_cpt;
}

// =====================================
// 8) Log de depuración (opcional)
// =====================================

add_action( 'init', function () {
    if ( ! function_exists( 'bb_custom_price_quote' ) ) {
        error_log( 'Bubbles Booking: ⚠️ bb_custom_price_quote() NO está cargada.' );
    } else {
        error_log( 'Bubbles Booking: ✅ bb_custom_price_quote() cargada correctamente.' );
    }
} );

// =====================================
// 9) Shortcode [bubbles_wizard] – asegurar que exista
// =====================================

add_action( 'init', function () {

    // Si el shortcode ya existe (registrado dentro de Bubbles_Wizard), no hacemos nada
    if ( shortcode_exists( 'bubbles_wizard' ) ) {
        return;
    }

    // Si existe la clase Bubbles_Wizard, la instanciamos
    if ( class_exists( 'Bubbles_Wizard' ) ) {
        $GLOBALS['bubbles_wizard_instance'] = new Bubbles_Wizard();
        if ( shortcode_exists( 'bubbles_wizard' ) ) {
            return;
        }
    }

    // Si hay una función específica para el shortcode, la usamos
    if ( function_exists( 'bubbles_wizard_shortcode' ) ) {
        add_shortcode( 'bubbles_wizard', 'bubbles_wizard_shortcode' );
        return;
    }

    // Fallback: mensaje de error amigable
    add_shortcode( 'bubbles_wizard', function () {
        return '<div style="border:1px solid #f87171;padding:12px;border-radius:8px;background:#fef2f2;color:#7f1d1d;">
            <strong>Bubbles Booking:</strong> El shortcode <code>[bubbles_wizard]</code> está activo, 
            pero no se encontró la implementación del wizard. Revisa <code>includes/UI/Wizard/bubbles-wizard.php</code>.
        </div>';
    } );
} );

// =====================================
// 10) Admin module (menús Bubbles Booking)
// =====================================

if ( is_admin() ) {
    $bb_admin_module_file = BB_PLUGIN_DIR . 'includes/UI/Admin/bb-admin-module.php';

    if ( file_exists( $bb_admin_module_file ) ) {
        require_once $bb_admin_module_file;

        if ( class_exists( 'BB_Admin_Module' ) ) {
            new BB_Admin_Module();
        } else {
            error_log( 'Bubbles Booking: ❌ BB_Admin_Module no existe después de incluir bb-admin-module.php.' );
        }
    } else {
        error_log( 'Bubbles Booking: ❌ No se encontró ' . $bb_admin_module_file );
    }
}

// =====================================
// 11) Helper de opciones (Stripe) – usado por AJAX
// =====================================

/**
 * Devuelve las opciones globales para Stripe (legacy).
 *
 * ⚠️ Actualmente se usa SOLO por bb_create_payment_intent().
 *    Los settings de Google Maps ya se manejan con BB_Settings_Map_Repo / Service.
 */
function bubbles_booking_get_options() {
    $option_name     = 'bubbles_booking_options';
    $default_options = array(
        'google_maps_api_key' => '',
        'stripe_secret_key'   => '',
        'stripe_public_key'   => '',
    );

    $options = get_option( $option_name, array() );
    if ( ! is_array( $options ) ) {
        $options = array();
    }

    $options = wp_parse_args( $options, $default_options );

    return $options;
}

// =====================================
// 12) AJAX: crear PaymentIntent de Stripe
// =====================================

add_action( 'wp_ajax_bb_create_payment_intent', 'bb_create_payment_intent' );
add_action( 'wp_ajax_nopriv_bb_create_payment_intent', 'bb_create_payment_intent' );

function bb_create_payment_intent() {

    if ( ! function_exists( 'bubbles_booking_get_options' ) ) {
        wp_send_json_error( array( 'message' => 'Plugin options not available.' ) );
    }

    $options = bubbles_booking_get_options();
    $secret  = isset( $options['stripe_secret_key'] ) ? trim( $secret = $options['stripe_secret_key'] ) : '';

    if ( empty( $secret ) ) {
        wp_send_json_error( array( 'message' => 'Stripe secret key is missing.' ) );
    }

    $amount   = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
    $currency = isset( $_POST['currency'] ) ? sanitize_text_field( $_POST['currency'] ) : 'usd';

    if ( $amount <= 0 ) {
        wp_send_json_error( array( 'message' => 'Invalid amount.' ) );
    }

    $amount_cents = (int) round( $amount * 100 );

    $body = array(
        'amount'                         => $amount_cents,
        'currency'                       => $currency,
        'automatic_payment_methods[enabled]' => 'true',
    );

    $response = wp_remote_post(
        'https://api.stripe.com/v1/payment_intents',
        array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
            ),
            'body'    => $body,
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => $response->get_error_message() ) );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $raw  = wp_remote_retrieve_body( $response );
    $data = json_decode( $raw, true );

    if ( $code !== 200 || ! is_array( $data ) || empty( $data['client_secret'] ) ) {
        $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Stripe API error.';
        wp_send_json_error( array( 'message' => $error_message ) );
    }

    wp_send_json_success( array(
        'client_secret'     => $data['client_secret'],
        'payment_intent_id' => $data['id'],
    ) );
}
