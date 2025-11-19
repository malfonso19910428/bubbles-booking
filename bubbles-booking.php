<?php
/*
Plugin Name: Bubbles Booking
Description: Shortcodes y funciones para el proceso de booking de car detailing.
Version: 1.0.5
Author: Mily
*/

// Seguridad básica
if (!defined('ABSPATH')) exit;

// ==============================
// Constantes
// ==============================
define('BB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BB_PLUGIN_URL', plugin_dir_url(__FILE__));
require_once BB_PLUGIN_DIR . 'includes/catalog/addons-catalog.php';
// Core
require_once BB_PLUGIN_DIR . 'includes/core/class-bubbles-bookings.php';
require_once BB_PLUGIN_DIR . 'includes/core/class-bubbles-packages.php';

// ==============================
// Encolar JS y CSS (picker + wizard)
// ==============================
add_action('wp_enqueue_scripts', function () {
    // --- JS ---
    $picker_js = BB_PLUGIN_DIR . 'assets/js/bb-picker.js';
    $wizard_js = BB_PLUGIN_DIR . 'assets/js/bb-wizard.js';

    // Picker (requerido)
    wp_enqueue_script(
        'bb-picker',
        BB_PLUGIN_URL . 'assets/js/bb-picker.js',
        array(), // dependencias (ej: ['jquery'] si lo necesitas)
        file_exists($picker_js) ? filemtime($picker_js) : '1.0.0',
        true // footer
    );

    // Wizard helper (opcional)
    if (file_exists($wizard_js)) {
        wp_enqueue_script(
            'bb-wizard',
            BB_PLUGIN_URL . 'assets/js/bb-wizard.js',
            array('bb-picker'),
            file_exists($wizard_js) ? filemtime($wizard_js) : '1.0.0',
            true
        );
    }

    // --- CSS ---
    $wizard_css = BB_PLUGIN_DIR . 'assets/css/bb-wizard.css';
    $picker_css = BB_PLUGIN_DIR . 'assets/css/bb-picker.css';

    if (file_exists($wizard_css)) {
        wp_enqueue_style(
            'bb-wizard',
            BB_PLUGIN_URL . 'assets/css/bb-wizard.css',
            array(),
            filemtime($wizard_css)
        );
    }

    if (file_exists($picker_css)) {
        wp_enqueue_style(
            'bb-picker-css',
            BB_PLUGIN_URL . 'assets/css/bb-picker.css',
            array('bb-wizard'),
            filemtime($picker_css)
        );
    }
}, 5);

// ==============================
// INCLUDES PRINCIPALES
// ==============================

// Wizard principal (flujo booking)
$wizard_inc = BB_PLUGIN_DIR . 'includes/bubbles-wizard.php';
if (file_exists($wizard_inc)) {
    require_once $wizard_inc;
}

// Selector de vehículos (Vehicle Picker)
$picker_inc = BB_PLUGIN_DIR . 'includes/bubbles_vehicle_picker.php';
if (file_exists($picker_inc)) {
    require_once $picker_inc;
}

// Módulo de precios (para el botón "See my price")
$pricing_inc = BB_PLUGIN_DIR . 'includes/bubbles-custom-price.php';
if (file_exists($pricing_inc)) {
    require_once $pricing_inc;
} else {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Bubbles Booking:</strong> Falta <code>includes/bubbles-custom-price.php</code>.</p></div>';
    });
}

// (Opcional) CPT + helpers si quieres guardar/listar/borrar vehículos.
$vehicles_cpt = BB_PLUGIN_DIR . 'includes/bubbles-vehicles-cpt.php';
if (file_exists($vehicles_cpt)) {
    require_once $vehicles_cpt;
}

// ==============================
// LOG DE DEPURACIÓN (opcional)
// ==============================
add_action('init', function () {
    if (!function_exists('bb_custom_price_quote')) {
        error_log('Bubbles Booking: ⚠️ bb_custom_price_quote() NO está cargada.');
    } else {
        error_log('Bubbles Booking: ✅ bb_custom_price_quote() cargada correctamente.');
    }
});

// ==============================
// Shortcode [bubbles_wizard] – asegurarlo SIEMPRE
// ==============================
add_action('init', function () {

    // Si ya existe (por ejemplo, registrado dentro de Bubbles_Wizard), no hacemos nada
    if (shortcode_exists('bubbles_wizard')) {
        return;
    }

    // Si existe la clase Bubbles_Wizard, la instanciamos
    if (class_exists('Bubbles_Wizard')) {
        // Muchas veces el constructor ya hace add_shortcode(...)
        $GLOBALS['bubbles_wizard_instance'] = new Bubbles_Wizard();
        if (shortcode_exists('bubbles_wizard')) {
            return;
        }
    }

    // Si hay una función específica para el shortcode, la usamos
    if (function_exists('bubbles_wizard_shortcode')) {
        add_shortcode('bubbles_wizard', 'bubbles_wizard_shortcode');
        return;
    }

    // Fallback: al menos mostrar algo y no dejar el texto plano
    add_shortcode('bubbles_wizard', function () {
        return '<div style="border:1px solid #f87171;padding:12px;border-radius:8px;background:#fef2f2;color:#7f1d1d;">
            <strong>Bubbles Booking:</strong> El shortcode <code>[bubbles_wizard]</code> está activo, 
            pero no se encontró la implementación del wizard. Revisa <code>includes/bubbles-wizard.php</code>.
        </div>';
    });
});

// ==============================
// Admin menu: Bubbles Booking → Settings (Google Maps API Key)
// ==============================
add_action('admin_menu', 'bubbles_booking_register_settings_page');

function bubbles_booking_register_settings_page() {
    add_menu_page(
        'Bubbles Booking Settings',               // Título de la página
        'Bubbles Booking',                        // Texto del menú
        'manage_options',                         // Capacidad necesaria
        'bubbles-booking-settings',               // slug único
        'bubbles_booking_render_settings_page',   // Función que pinta la página
        'dashicons-calendar-alt',                 // Icono en el menú
        56                                        // Posición aproximada
    );
}

function bubbles_booking_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $option_name = 'bubbles_booking_options';

    // Cargar opciones actuales (o por defecto)
    $options = get_option($option_name, array(
        'google_maps_api_key' => '',
    ));

    // Si enviaron el formulario
    if (isset($_POST['bubbles_booking_save_settings'])) {
        check_admin_referer('bubbles_booking_save_settings');

        $new_api_key = isset($_POST['google_maps_api_key'])
            ? sanitize_text_field($_POST['google_maps_api_key'])
            : '';

        $options['google_maps_api_key'] = $new_api_key;

        update_option($option_name, $options);

        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $current_api_key = isset($options['google_maps_api_key']) ? $options['google_maps_api_key'] : '';
    ?>
    <div class="wrap">
        <h1>Bubbles Booking – Settings</h1>
        <p>Configure here the global settings for the Bubbles Booking plugin.</p>

        <form method="post" action="">
            <?php wp_nonce_field('bubbles_booking_save_settings'); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="google_maps_api_key">Google Maps API Key</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="google_maps_api_key"
                                   id="google_maps_api_key"
                                   value="<?php echo esc_attr($current_api_key); ?>"
                                   class="regular-text"
                                   style="width: 420px;">
                            <p class="description">
                                This key must have <strong>Maps JavaScript API</strong> and <strong>Places API</strong> enabled in your Google Cloud project.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" name="bubbles_booking_save_settings" class="button button-primary">
                    Save changes
                </button>
            </p>
        </form>
    </div>
    <?php
}

// ==============================
// Frontend: Google Places + JS de autocomplete UNA sola vez
// ==============================
add_action('wp_enqueue_scripts', 'bubbles_booking_enqueue_address_assets');

function bubbles_booking_enqueue_address_assets() {
    if (is_admin()) return;

    global $post;
    if (!is_a($post, 'WP_Post')) return;

    // Solo si la página tiene el shortcode del wizard
    if (strpos($post->post_content, '[bubbles_wizard') === false) {
        return;
    }

    // Leer settings
    $options = get_option('bubbles_booking_options', array());
    $api_key = isset($options['google_maps_api_key']) ? trim($options['google_maps_api_key']) : '';

    // Permitir override por filter si algún día lo necesitas
    $api_key = apply_filters('bubbles_google_maps_api_key', $api_key);

    if (empty($api_key)) {
        // Sin API key no cargamos Google
        return;
    }

    // Script de Google Places
    wp_enqueue_script(
        'bubbles-google-places',
        'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&libraries=places',
        array(),
        null,
        true
    );

    // Script propio para conectar el autocomplete con el input
    $js_url = BB_PLUGIN_URL . 'assets/js/bb-address-autocomplete.js';

    wp_enqueue_script(
        'bubbles-address-autocomplete',
        $js_url,
        array('bubbles-google-places'),
        '1.0',
        true
    );
}
