<?php
/**
 * Bubbles Vehicle Picker (shortcode)
 * Shortcode: [bubbles_vehicle_picker]
 * - HTML + validaciones en PHP
 * - Sin JS inline (lo hace assets/js/bb-picker.js)
 */
if (!defined('ABSPATH')) exit;
// prueba sftp henry
if (!class_exists('Bubbles_Vehicle_Picker')) {

class Bubbles_Vehicle_Picker {
    public function __construct() {
        add_shortcode('bubbles_vehicle_picker', array($this, 'render_form'));
    }

    public function render_form() {
        $has_cpt = function_exists('bubbles_save_vehicle')
            && function_exists('bubbles_list_my_vehicles')
            && function_exists('bubbles_delete_vehicle');

        $output = '';
        $errors = array();

        $prev = array(
            'year'  => isset($_POST['car_year'])  ? sanitize_text_field($_POST['car_year'])  : '',
            'make'  => isset($_POST['car_make'])  ? sanitize_text_field($_POST['car_make'])  : '',
            'model' => isset($_POST['car_model']) ? sanitize_text_field($_POST['car_model']) : '',
            'color' => isset($_POST['car_color']) ? sanitize_text_field($_POST['car_color']) : '',
        );

        // Remove (CPT)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bb_vehicle_remove_cpt']) && $has_cpt) {
            if ( ! isset($_POST['bb_vehicle_nonce']) || ! wp_verify_nonce($_POST['bb_vehicle_nonce'], 'bb_vehicle_form') ) {
                $errors[] = 'Security validation failed. Please try again.';
            } else {
                $pid = (int) $_POST['bb_vehicle_remove_cpt'];
                if (!bubbles_delete_vehicle($pid)) $errors[] = 'Unable to remove this vehicle.';
            }
        }

        // Save
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bb_vehicle_submit'])) {
            if ( ! isset($_POST['bb_vehicle_nonce']) || ! wp_verify_nonce($_POST['bb_vehicle_nonce'], 'bb_vehicle_form') ) {
                $errors[] = 'Security validation failed. Please try again.';
            }

            $year  = $prev['year'];
            $make  = $prev['make'];
            $model = $prev['model'];
            $color = $prev['color'];

            if ($year === '' || !ctype_digit($year)) {
                $errors[] = 'Please enter a valid numeric year.';
            } else {
                $y = (int) $year;
                $maxY = (int) date('Y') + 1;
                if ($y < 1980 || $y > $maxY) $errors[] = 'Year must be between 1980 and ' . $maxY . '.';
            }
            if ($make === '')  $errors[] = 'Please select a make.';
            if ($model === '') $errors[] = 'Please select a model.';
            if ($color === '') $errors[] = 'Please enter a color.';

            if (empty($errors)) {
                if ($has_cpt) {
                    $post_id = bubbles_save_vehicle(array(
                        'year'  => $year,
                        'make'  => $make,
                        'model' => $model,
                        'color' => $color,
                    ));
                    if ($post_id) update_post_meta($post_id, 'updated_at', time());
                }
                $vehicle = trim("$year $make $model ($color)");
                $output .= '<div class="notice notice-success"><p><strong>Saved:</strong> ' . esc_html($vehicle) . '</p></div>';
                $prev = array('year'=>'','make'=>'','model'=>'','color'=>'');
            }
        }

        if (!empty($errors)) {
            $output .= '<div class="notice notice-error"><p><strong>Please fix the following:</strong></p><ul>';
            foreach ($errors as $e) $output .= '<li>' . esc_html($e) . '</li>';
            $output .= '</ul></div>';
        }

        // --- HTML del formulario ---
        $output .= '
        <div class="bb-wrap">
          <form method="post" id="bb-form" class="bb-form" novalidate>
            ' . wp_nonce_field('bb_vehicle_form', 'bb_vehicle_nonce', true, false) . '

            <div class="bb-steps">
                <div class="bb-step">
                    <label for="bb-year" class="bb-label">Year</label>
                    <input type="text" name="car_year" id="bb-year" class="bb-input" placeholder="Type or pick (e.g., 2020)" value="' . esc_attr($prev['year']) . '" autocomplete="off" inputmode="numeric" pattern="[0-9]*">
                    <div id="bb-year-suggest" class="bb-suggest"></div>
                </div>

                <div class="bb-step">
                    <label for="bb-make" class="bb-label">Make</label>
                    <input type="text" name="car_make" id="bb-make" class="bb-input" placeholder="Type or pick (e.g., Toyota or T…)" value="' . esc_attr($prev['make']) . '" autocomplete="off">
                    <div id="bb-make-suggest" class="bb-suggest"></div>
                </div>

                <div class="bb-step">
                    <label for="bb-model" class="bb-label">Model</label>
                    <input type="text" name="car_model" id="bb-model" class="bb-input" placeholder="Type or pick" value="' . esc_attr($prev['model']) . '" autocomplete="off">
                    <div id="bb-model-suggest" class="bb-suggest"></div>
                </div>

                <div class="bb-step">
                    <label for="bb-color" class="bb-label">Color</label>
                    <input type="text" name="car_color" id="bb-color" class="bb-input" placeholder="Type or pick a color" value="' . esc_attr($prev['color']) . '" autocomplete="off">
                    <div id="bb-color-suggest" class="bb-suggest"></div>
                </div>
            </div>

            <div class="bb-actions">
                <button type="submit" name="bb_vehicle_submit" class="button button-primary">See my price</button>
                <button type="button" id="bb-help" class="button button-secondary">I can’t find my car</button>
            </div>

            <div id="bb-help-msg" class="notice" style="display:none;">
                <p><strong>Can’t find your car?</strong><br>
                Try selecting a different year or a similar model to find the one closest to your car.</p>
                <p>If you need assistance, please call 
                    <a href="tel:+18552572623">+1 855 257-2623</a>.
                </p>
            </div>

            <p class="bb-muted">
              🛈 We store your vehicles for <strong>1 year</strong> to make future bookings easier. 
              Your information is not shared with third parties.
              <a href="/privacy-policy">Learn more</a>
            </p>
          </form>
        ';

        if ($has_cpt) {
            $saved_posts = bubbles_list_my_vehicles(30);
            $output .= '<div id="bb-saved" class="bb-saved"><h3>Saved vehicles</h3>';
            if (!empty($saved_posts)) {
                $output .= '<ul class="bb-list">';
                foreach ($saved_posts as $p) {
                    $year  = get_post_meta($p->ID, 'year', true);
                    $make  = get_post_meta($p->ID, 'make', true);
                    $model = get_post_meta($p->ID, 'model', true);
                    $color = get_post_meta($p->ID, 'color', true);
                    $title = esc_html("$year $make $model ($color)");

                    $output .= '<li class="bb-list-item">';
                    $output .= '<span class="bb-vehicle-title"><strong>' . $title . '</strong></span> ';
                    $output .= '<form method="post" class="bb-inline-form">';
                    $output .= wp_nonce_field('bb_vehicle_form', 'bb_vehicle_nonce', true, false);
                    $output .= '<input type="hidden" name="bb_vehicle_remove_cpt" value="' . esc_attr($p->ID) . '">';
                    $output .= '<button type="submit" class="button button-secondary">Remove</button>';
                    $output .= '</form>';
                    $output .= '</li>';
                }
                $output .= '</ul>';
            } else {
                $output .= '<div class="notice"><p>No saved vehicles yet.</p></div>';
            }
            $output .= '</div>';
        }

        $output .= '</div>'; // .bb-wrap

        return $output;
    }
}

new Bubbles_Vehicle_Picker();
}
