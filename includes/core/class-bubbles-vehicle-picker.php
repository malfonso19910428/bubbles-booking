<?php
/**
 * Bubbles Vehicle Picker
 * - Lógica PHP + validaciones
 * - Usa la plantilla templates/wizard-step-vehicle.php para el HTML
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('Bubbles_Vehicle_Picker')) {

class Bubbles_Vehicle_Picker {

    public function __construct() {
        // Si quieres encolar JS/CSS específicos, aquí.
        // add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        // vacío
    }

    /**
     * Prepara datos y renderiza la plantilla del Step 1 (Vehicle)
     */
    public function render_form() {
        // ¿Existen las funciones del CPT?
        $has_cpt = function_exists('bubbles_save_vehicle')
            && function_exists('bubbles_list_my_vehicles')
            && function_exists('bubbles_delete_vehicle');

        $errors  = array();
        $message = '';

        // 🔹 1) LEER LO QUE VIENE DEL POST EN VARIABLES SEPARADAS
        $raw_year  = isset($_POST['car_year'])  ? sanitize_text_field($_POST['car_year'])  : '';
        $raw_make  = isset($_POST['car_make'])  ? sanitize_text_field($_POST['car_make'])  : '';
        $raw_model = isset($_POST['car_model']) ? sanitize_text_field($_POST['car_model']) : '';
        $raw_color = isset($_POST['car_color']) ? sanitize_text_field($_POST['car_color']) : '';

        // Vehículo que ve el usuario en el formulario
        $prev = array(
            'year'  => $raw_year,
            'make'  => $raw_make,
            'model' => $raw_model,
            'color' => $raw_color,
        );

        // 🔹 2) ESTADO GLOBAL QUE DEBEMOS ARRASTRAR
        // JSON con la lista de vehículos ya añadidos
        $bb_vehicles_json = '';
        if (isset($_POST['bb_vehicles'])) {
            $tmp = wp_unslash($_POST['bb_vehicles']);
            $bb_vehicles_json = is_string($tmp) ? $tmp : '';
        }

        // Paquete ya elegido (si existe)
        $bb_package = isset($_POST['bb_package'])
            ? sanitize_text_field($_POST['bb_package'])
            : '';

        // Add-ons ya elegidos (si existen)
        $addons_current = array();
        if (isset($_POST['addons']) && is_array($_POST['addons'])) {
            $addons_current = array_map('sanitize_text_field', $_POST['addons']);
        }

        // -------- REMOVE VEHICLE --------
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['bb_vehicle_remove_cpt'])
            && $has_cpt
        ) {
            if (
                ! isset($_POST['bb_vehicle_nonce'])
                || ! wp_verify_nonce($_POST['bb_vehicle_nonce'], 'bb_vehicle_form')
            ) {
                $errors[] = 'Security validation failed. Please try again.';
            } else {
                $pid = (int) $_POST['bb_vehicle_remove_cpt'];
                if (!bubbles_delete_vehicle($pid)) {
                    $errors[] = 'Unable to remove this vehicle.';
                }
            }
        }

        // -------- SAVE VEHICLE (cuando estamos en el paso vehicle) --------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bb_vehicle_submit'])) {

            if (
                ! isset($_POST['bb_vehicle_nonce'])
                || ! wp_verify_nonce($_POST['bb_vehicle_nonce'], 'bb_vehicle_form')
            ) {
                $errors[] = 'Security validation failed. Please try again.';
            }

            // Usamos SIEMPRE los valores crudos del POST
            $year  = $raw_year;
            $make  = $raw_make;
            $model = $raw_model;
            $color = $raw_color;

            // Validación de año
            if ($year === '' || !ctype_digit($year)) {
                $errors[] = 'Please enter a valid numeric year.';
            } else {
                $y    = (int) $year;
                $maxY = (int) date('Y') + 1;
                if ($y < 1980 || $y > $maxY) {
                    $errors[] = 'Year must be between 1980 and ' . $maxY . '.';
                }
            }

            if ($make === '')  $errors[] = 'Please select a make.';
            if ($model === '') $errors[] = 'Please select a model.';
            if ($color === '') $errors[] = 'Please enter a color.';

            // Si no hay errores, guardamos en el CPT
            if (empty($errors)) {
                if ($has_cpt) {
                    $post_id = bubbles_save_vehicle(array(
                        'year'  => $year,
                        'make'  => $make,
                        'model' => $model,
                        'color' => $color,
                    ));

                    if ($post_id) {
                        update_post_meta($post_id, 'updated_at', time());
                    }
                }

                $vehicle = trim("$year $make $model ($color)");
                $message = '<div class="notice notice-success"><p><strong>Saved:</strong> ' . esc_html($vehicle) . '</p></div>';

                // ✅ SOLO DESPUÉS DE GUARDAR LIMPIAMOS LO QUE VE EL FORMULARIO
                $prev = array(
                    'year'  => '',
                    'make'  => '',
                    'model' => '',
                    'color' => '',
                );

                // Opcional: limpiar POST de los campos visibles
                unset($_POST['car_year'], $_POST['car_make'], $_POST['car_model'], $_POST['car_color']);
            }
        }

        // -------- LISTADO DE VEHÍCULOS GUARDADOS --------
        $saved_posts = array();
        if ($has_cpt) {
            $saved_posts = bubbles_list_my_vehicles(30);
        }

        // -------- Plantilla --------
        $template = trailingslashit(BB_PLUGIN_DIR) . 'templates/wizard-step-vehicle.php';
        if (!file_exists($template)) {
            return '<div class="notice notice-error"><p><strong>Bubbles Booking:</strong> Missing template <code>templates/wizard-step-vehicle.php</code>.</p></div>';
        }

        // Variables disponibles en la plantilla:
        // $prev, $errors, $message, $saved_posts, $has_cpt,
        // $bb_vehicles_json, $bb_package, $addons_current
        ob_start();
        include $template;
        return ob_get_clean();
    }

    /**
     * Guarda el vehículo leyendo directamente de $_POST.
     * Se usa cuando el wizard recibe bb_vehicle_submit y salta al paso "package".
     * Devuelve true si se guardó correctamente, false si hubo errores.
     */
    public function save_from_post() {
        // Asegurar que existen funciones del CPT
        $has_cpt = function_exists('bubbles_save_vehicle')
            && function_exists('bubbles_list_my_vehicles')
            && function_exists('bubbles_delete_vehicle');

        if (!$has_cpt) {
            return false;
        }

        // Solo actuamos si realmente viene del botón del picker
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            ! isset($_POST['bb_vehicle_submit'])
        ) {
            return false;
        }

        // Leer valores crudos desde $_POST
        $year  = isset($_POST['car_year'])  ? sanitize_text_field($_POST['car_year'])  : '';
        $make  = isset($_POST['car_make'])  ? sanitize_text_field($_POST['car_make'])  : '';
        $model = isset($_POST['car_model']) ? sanitize_text_field($_POST['car_model']) : '';
        $color = isset($_POST['car_color']) ? sanitize_text_field($_POST['car_color']) : '';

        // Validación básica
        if ($year === '' || !ctype_digit($year)) {
            return false;
        } else {
            $y    = (int) $year;
            $maxY = (int) date('Y') + 1;
            if ($y < 1980 || $y > $maxY) {
                return false;
            }
        }

        if ($make === '' || $model === '' || $color === '') {
            return false;
        }

        // Guardar/actualizar vehículo en el CPT
        $post_id = bubbles_save_vehicle(array(
            'year'  => $year,
            'make'  => $make,
            'model' => $model,
            'color' => $color,
        ));

        if ($post_id) {
            update_post_meta($post_id, 'updated_at', time());
            return true;
        }

        return false;
    }
}

// Instancia GLOBAL
global $bubbles_vehicle_picker;
$bubbles_vehicle_picker = new Bubbles_Vehicle_Picker();

}
