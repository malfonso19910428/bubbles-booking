<?php
/**
 * Bubbles Booking - Wizard (templated)
 * Shortcode: [bubbles_wizard]
 *
 * Flujo:
 *  - vehicle
 *  - package
 *  - addons
 *  - address
 *  - date
 *  - confirm
 *
 * Cada paso se renderiza con una plantilla en /templates/wizard-step-*.php
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('Bubbles_Wizard')) {

class Bubbles_Wizard {

    /** Orden de pasos (contact + review unificados en confirm) */
    private $steps  = array('vehicle','package','addons','address','date','confirm');

    /** Errores por paso (ej. $errors['date']) */
    private $errors = array();

    public function __construct() {
        add_shortcode('bubbles_wizard', array($this, 'render'));
    }

    /* ------------------------- Helpers ------------------------- */

    /** Obtiene valor posteado (string o array saneado) */
    public function posted($key, $default = '') {
        return isset($_POST[$key])
            ? (is_array($_POST[$key]) ? array_map('sanitize_text_field', $_POST[$key]) : sanitize_text_field($_POST[$key]))
            : $default;
    }

    /** Obtiene arreglo posteado saneado (o []) */
    public function posted_array($key) {
        return isset($_POST[$key]) && is_array($_POST[$key]) ? array_map('sanitize_text_field', $_POST[$key]) : array();
    }

    /** Input hidden */
    public function hidden($name, $value) {
        return '<input type="hidden" name="'.esc_attr($name).'" value="'.esc_attr($value).'" />';
    }

    /** Hidden del vehículo */
    public function hidden_vehicle_fields() {
        $html  = $this->hidden('car_year',  $this->posted('car_year'));
        $html .= $this->hidden('car_make',  $this->posted('car_make'));
        $html .= $this->hidden('car_model', $this->posted('car_model'));
        return $html;
    }

    /** Hidden de add-ons seleccionados */
    public function hidden_addons_fields() {
        $html = '';
        foreach ($this->posted_array('addons') as $slug) {
            $html .= $this->hidden('addons[]', $slug);
        }
        return $html;
    }

    /** Hidden de la dirección */
    public function hidden_address_fields() {
        $html  = $this->hidden('bb_address_type',  $this->posted('bb_address_type'));
        $html .= $this->hidden('bb_address',       $this->posted('bb_address'));
        $html .= $this->hidden('bb_address_extra', $this->posted('bb_address_extra'));

        // place_id de Google
        $html .= $this->hidden('bb_place_id',      $this->posted('bb_place_id'));

        // Campos estructurados para el autocomplete
        $html .= $this->hidden('bb_address_street', $this->posted('bb_address_street'));
        $html .= $this->hidden('bb_address_city',   $this->posted('bb_address_city'));
        $html .= $this->hidden('bb_address_state',  $this->posted('bb_address_state'));
        $html .= $this->hidden('bb_address_zip',    $this->posted('bb_address_zip'));
        $html .= $this->hidden('bb_address_lat',    $this->posted('bb_address_lat'));
        $html .= $this->hidden('bb_address_lng',    $this->posted('bb_address_lng'));
        return $html;
    }

    /** Hidden de fecha y hora */
    public function hidden_date_fields() {
        $html  = $this->hidden('bb_date', $this->posted('bb_date'));
        $html .= $this->hidden('bb_time', $this->posted('bb_time'));
        return $html;
    }

    /** Hidden de contacto (para reinyectar si hace falta) */
    public function hidden_contact_fields() {
        $html  = $this->hidden('bb_name',  $this->posted('bb_name'));
        $html .= $this->hidden('bb_phone', $this->posted('bb_phone'));
        $html .= $this->hidden('bb_email', $this->posted('bb_email'));
        $html .= $this->hidden('bb_notes', $this->posted('bb_notes'));
        return $html;
    }

    /* ------------------------- Render principal ------------------------- */

    public function render() {

        /* 1) Confirmación final: enviar a WooCommerce checkout */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bb_confirm_booking'])) {
            return $this->handle_final_submit();
        }

        /* 2) Flujo normal de pasos */
        $requested_step = isset($_POST['bb_step']) ? sanitize_text_field($_POST['bb_step']) : 'vehicle';
        $current_step   = in_array($requested_step, $this->steps, true) ? $requested_step : 'vehicle';

        // Validación especial del paso "address"
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['bb_step']) &&
            $_POST['bb_step'] === 'address' &&
            isset($_POST['bb_continue'])
        ) {
            if (!is_array($this->errors)) {
                $this->errors = array();
            }

            $address_type = trim($this->posted('bb_address_type'));
            $address      = trim($this->posted('bb_address'));
            $city         = trim($this->posted('bb_address_city'));
            $state        = trim($this->posted('bb_address_state'));

            unset($this->errors['address']);

            if ($address_type === '') {
                $this->errors['address'] = 'Please select if this is your home, work or other address.';
            } elseif ($address === '') {
                $this->errors['address'] = 'Please enter your service address.';
            } elseif ($city === '' || $state === '') {
                $this->errors['address'] = 'Please select a complete address from the suggestions.';
            }

            if (!empty($this->errors['address'])) {
                $current_step = 'address';
                unset($_POST['bb_continue']);
            }
        }

        // Validación especial del paso "date"
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['bb_step']) &&
            $_POST['bb_step'] === 'date' &&
            isset($_POST['bb_continue'])
        ) {
            $bb_date = isset($_POST['bb_date']) ? sanitize_text_field($_POST['bb_date']) : '';
            $date_ok = true;

            if (empty($bb_date)) {
                $date_ok = false;
            } elseif (class_exists('Bubbles_Availability')) {
                if (!Bubbles_Availability::is_date_bookable($bb_date)) {
                    $date_ok = false;
                }
            } else {
                // Fallback básico: no permitir hoy ni pasados
                $today = current_time('Y-m-d');
                if ($bb_date <= $today) {
                    $date_ok = false;
                }
            }

            if (!$date_ok) {
                $current_step = 'date';
                $this->errors['date'] = __('Please choose a valid working day. Same-day and past dates are not available.', 'bubbles-booking');
                unset($_POST['bb_continue']);
            }
        }

        // Vehicle Picker -> Package (cuando el picker envía su submit)
        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['bb_vehicle_submit'])) {
            $current_step = 'package';
        }

        // Navegación Back/Continue según el índice del paso actual
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            if (isset($_POST['bb_continue'])) {
                $idx = array_search($current_step, $this->steps, true);
                if ($idx !== false && ($idx + 1) < count($this->steps)) {
                    $current_step = $this->steps[$idx + 1];
                }
            } elseif (isset($_POST['bb_back'])) {
                $idx = array_search($current_step, $this->steps, true);
                if ($idx !== false && $idx > 0) {
                    $current_step = $this->steps[$idx - 1];
                }
            }
        }

        // Layout principal (sin CSS inline, solo estructura)
        $sidebar = $this->render_sidebar($current_step);
        $content = $this->render_step($current_step);

        $out  = '<div class="bb-wizard bb-layout">';
        $out .= $sidebar;
        $out .= '<div class="bb-panel">'.$content.'</div>';
        $out .= '</div>';

        return $out;
    }

    /* ------------------------- Sidebar ------------------------- */

    private function render_sidebar($current_step) {
        $labels = array(
            'vehicle' => array('label'=>'Vehicle info','meta'=>'Pick your vehicle'),
            'package' => array('label'=>'Package & price','meta'=>'Choose a package'),
            'addons'  => array('label'=>'Add-ons','meta'=>'Optional extras'),
            'address' => array('label'=>'Address','meta'=>'Where should we go?'),
            'date'    => array('label'=>'Date & time','meta'=>'Pick a slot'),
            'confirm' => array('label'=>'Confirm & pay','meta'=>'Contact + final review'),
        );

        $idx_current = array_search($current_step, $this->steps, true);

        $html = '<aside class="bb-sidebar"><h3>Booking steps</h3><ul class="bb-steps-list">';
        foreach ($this->steps as $i => $key) {
            $cls   = ($i < $idx_current) ? 'done' : (($i === $idx_current) ? 'active' : 'pending');
            $label = esc_html($labels[$key]['label']);
            $meta  = esc_html($labels[$key]['meta']);
            $html .= '<li class="'.$cls.'"><div class="label">'.$label.'</div><div class="meta">'.$meta.'</div></li>';
        }
        $html .= '</ul></aside>';
        return $html;
    }

    /* ------------------------- Dispatch por paso ------------------------- */

    private function render_step($step) {
        switch ($step) {
            case 'vehicle':
                return $this->render_step_vehicle();
            case 'package':
                return $this->render_step_package();
            case 'addons':
                return $this->render_step_addons();
            case 'address':
                return $this->render_step_address();
            case 'date':
                return $this->render_step_date();
            case 'confirm':
                return $this->render_step_confirm();
            default:
                return '<p class="notice-error">Invalid step.</p>';
        }
    }

    /* ------------------------- STEP 1: VEHICLE ------------------------- */

    protected function render_step_vehicle() {
        $wizard = $this; // por si quieres usar helpers en la plantilla
        ob_start();
        include plugin_dir_path(__FILE__) . '../templates/wizard-step-vehicle.php';
        return ob_get_clean();
    }

    /* ------------------------- STEP 2: PACKAGE ------------------------- */

    protected function render_step_package() {

        $vehicle = array(
            'year'  => $this->posted('car_year'),
            'make'  => $this->posted('car_make'),
            'model' => $this->posted('car_model'),
        );

        // 1) Obtener precios desde BB_Pricing (Bubbles Custom Pricing)
        $pricing_packages = function_exists('bb_custom_price_quote')
            ? bb_custom_price_quote($vehicle)
            : array();

        // 2) Metadatos extendidos de paquetes
        $meta_all = class_exists('Bubbles_Packages')
            ? Bubbles_Packages::get_all()
            : array();

        $selected_pkg = $this->posted('bb_package', '');

        $wizard = $this;

        ob_start();
        include plugin_dir_path(__FILE__) . '../templates/wizard-step-package.php';
        return ob_get_clean();
    }

    /* ------------------------- STEP 3: ADD-ONS ------------------------- */

    protected function render_step_addons() {

        $addons_catalog = apply_filters('bubbles_addons_catalog', array(
            array(
                'slug'  => 'heavy_pet_hair',
                'name'  => 'Heavy Pet Hair Removal',
                'desc'  => 'Intensive pet-hair removal from seats, carpets, and hard-to-reach areas.',
                'price' => 45,
            ),
            array(
                'slug'  => 'light_pet_hair',
                'name'  => 'Light Pet Hair Removal',
                'desc'  => 'Light pet-hair removal in visible areas.',
                'price' => 25,
            ),
            array(
                'slug'  => 'car_baby_seat',
                'name'  => 'Car Baby Seat',
                'desc'  => 'Detailed cleaning of the child car seat (accessible areas).',
                'price' => 25,
            ),
        ));

        $prev_addons  = $this->posted_array('addons');
        $selected_pkg = $this->posted('bb_package','');

        $wizard = $this;

        ob_start();
        include plugin_dir_path(__FILE__) . '../templates/wizard-step-addons.php';
        return ob_get_clean();
    }

    /* ------------------------- STEP 4: ADDRESS (SIN PLANTILLA) ------------------------- */

    protected function render_step_address() {

        $address_type  = $this->posted('bb_address_type','');
        $address       = $this->posted('bb_address','');
        $address_extra = $this->posted('bb_address_extra','');

        $errors = $this->errors;

        ob_start();
        ?>
        <form method="post" class="bb-step-form bb-step-address">
            <?php
            // Mantener datos de pasos anteriores
            echo $this->hidden_vehicle_fields();
            echo $this->hidden_addons_fields();
            echo $this->hidden_date_fields();
            echo $this->hidden_contact_fields();
            ?>
            <input type="hidden" name="bb_step" value="address">
            <input type="hidden" name="bb_package" value="<?php echo esc_attr($this->posted('bb_package')); ?>">

            <h3 class="bb-section-title">Step 4 · Address</h3>

            <?php if (!empty($errors['address'])): ?>
                <div class="bb-error">
                    <?php echo esc_html($errors['address']); ?>
                </div>
            <?php endif; ?>

            <div class="bb-field-row">
                <label>Address type</label>
                <label>
                    <input type="radio" name="bb_address_type" value="home" <?php checked($address_type, 'home'); ?> />
                    Home
                </label>
                <label>
                    <input type="radio" name="bb_address_type" value="work" <?php checked($address_type, 'work'); ?> />
                    Work
                </label>
                <label>
                    <input type="radio" name="bb_address_type" value="other" <?php checked($address_type, 'other'); ?> />
                    Other
                </label>
            </div>

            <div class="bb-field-row">
                <label for="bb_address">Address</label>
                <input
                    type="text"
                    id="bb_address"
                    name="bb_address"
                    value="<?php echo esc_attr($address); ?>"
                    placeholder="Street, city, state, ZIP"
                    autocomplete="off"
                    required
                />
                <p class="description">
                    Start typing your address and select one of the suggestions.
                </p>
            </div>

            <div class="bb-field-row">
                <label for="bb_address_extra">Apartment, suite, etc. (optional)</label>
                <input
                    type="text"
                    id="bb_address_extra"
                    name="bb_address_extra"
                    value="<?php echo esc_attr($address_extra); ?>"
                    placeholder="Apartment, suite, building, gate code, etc."
                />
            </div>

            <!-- Hidden fields para Google Places -->
            <input type="hidden" name="bb_place_id" value="<?php echo esc_attr($this->posted('bb_place_id')); ?>">
            <input type="hidden" name="bb_address_street" value="<?php echo esc_attr($this->posted('bb_address_street')); ?>">
            <input type="hidden" name="bb_address_city"   value="<?php echo esc_attr($this->posted('bb_address_city')); ?>">
            <input type="hidden" name="bb_address_state"  value="<?php echo esc_attr($this->posted('bb_address_state')); ?>">
            <input type="hidden" name="bb_address_zip"    value="<?php echo esc_attr($this->posted('bb_address_zip')); ?>">
            <input type="hidden" name="bb_address_lat"    value="<?php echo esc_attr($this->posted('bb_address_lat')); ?>">
            <input type="hidden" name="bb_address_lng"    value="<?php echo esc_attr($this->posted('bb_address_lng')); ?>">

            <div class="bb-actions">
                <button type="submit" name="bb_back" class="bb-btn bb-btn-secondary">
                    &laquo; Back
                </button>
                <button type="submit" name="bb_continue" class="bb-btn bb-btn-primary">
                    Continue
                </button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /* ------------------------- STEP 5: DATE ------------------------- */

    protected function render_step_date() {

        $bb_date = $this->posted('bb_date');
        $bb_time = $this->posted('bb_time');
        $pkg_id  = $this->posted('bb_package');

        $duration_hours = class_exists('Bubbles_Packages')
            ? Bubbles_Packages::get_duration($pkg_id)
            : 2.0;

        if (class_exists('Bubbles_Availability')) {
            $min_date = Bubbles_Availability::get_min_bookable_date();
            $max_date = Bubbles_Availability::get_max_bookable_date();
        } else {
            $today    = current_time('Y-m-d');
            $min_date = date('Y-m-d', strtotime($today . ' +1 day'));
            $max_date = date('Y-m-d', strtotime($min_date . ' +59 days'));
        }

        $min_year  = (int) substr($min_date, 0, 4);
        $min_month = (int) substr($min_date, 5, 2);
        $max_year  = (int) substr($max_date, 0, 4);
        $max_month = (int) substr($max_date, 5, 2);

        $cal_year  = isset($_POST['bb_cal_year'])  ? (int) $_POST['bb_cal_year']  : 0;
        $cal_month = isset($_POST['bb_cal_month']) ? (int) $_POST['bb_cal_month'] : 0;

        if ($cal_year < 1 || $cal_month < 1 || $cal_month > 12) {
            $year  = $min_year;
            $month = $min_month;
        } else {
            $year  = $cal_year;
            $month = $cal_month;
        }

        if (isset($_POST['bb_cal_prev'])) {
            $month--;
            if ($month < 1) { $month = 12; $year--; }
        }
        if (isset($_POST['bb_cal_next'])) {
            $month++;
            if ($month > 12) { $month = 1; $year++; }
        }

        $ym     = $year * 100 + $month;
        $ym_min = $min_year * 100 + $min_month;
        $ym_max = $max_year * 100 + $max_month;

        if ($ym < $ym_min) { $year = $min_year;  $month = $min_month; }
        if ($ym > $ym_max) { $year = $max_year;  $month = $max_month; }

        $first_ts      = strtotime("$year-$month-01");
        $days_in_month = (int) date('t', $first_ts);
        $first_weekday = (int) date('N', $first_ts); // 1=Mon
        $month_label   = date_i18n('F Y', $first_ts);

        $is_bookable = function($ymd) use ($min_date, $max_date) {
            if (class_exists('Bubbles_Availability')) {
                return Bubbles_Availability::is_date_bookable($ymd);
            }
            return ($ymd > current_time('Y-m-d'));
        };

        $slots = array();
        if (!empty($bb_date)) {
            if (class_exists('Bubbles_Availability')) {
                $slots = Bubbles_Availability::get_slots_for_date(
                    $bb_date,
                    array('duration_hours' => $duration_hours)
                );
            } else {
                $slots = array(
                    array('value'=>'09:00-11:00','label'=>'9:00 AM – 11:00 AM'),
                    array('value'=>'11:00-13:00','label'=>'11:00 AM – 1:00 PM'),
                    array('value'=>'13:00-15:00','label'=>'1:00 PM – 3:00 PM'),
                );
            }
        }

        $wizard = $this;
        $errors = $this->errors;

        $allow_prev = ($year * 100 + $month) > $ym_min;
        $allow_next = ($year * 100 + $month) < $ym_max;

        ob_start();
        include plugin_dir_path(__FILE__) . '../templates/wizard-step-date.php';
        return ob_get_clean();
    }

    /* ------------------------- STEP 6: CONFIRM ------------------------- */

    protected function render_step_confirm() {

        $vehicle = array(
            'year'  => $this->posted('car_year','—'),
            'make'  => $this->posted('car_make','—'),
            'model' => $this->posted('car_model','—'),
        );

        $pkg_id      = $this->posted('bb_package','');
        $addons_sel  = $this->posted_array('addons');
        $address_typ = $this->posted('bb_address_type','—');
        $address     = $this->posted('bb_address','—');
        $address_ext = $this->posted('bb_address_extra','');
        $bb_date     = $this->posted('bb_date','—');
        $bb_time     = $this->posted('bb_time','—');

        $bb_name  = $this->posted('bb_name','');
        $bb_phone = $this->posted('bb_phone','');
        $bb_email = $this->posted('bb_email','');
        $bb_notes = $this->posted('bb_notes','');

        $vehicle_for_price = array(
            'year'  => $this->posted('car_year'),
            'make'  => $this->posted('car_make'),
            'model' => $this->posted('car_model'),
        );

        $package_label = $pkg_id;
        $package_price = '';

        if (function_exists('bb_custom_price_quote')) {
            $quote = bb_custom_price_quote($vehicle_for_price);
            if (is_array($quote)) {
                foreach ($quote as $p) {
                    if (!empty($p['id']) && $p['id'] === $pkg_id) {
                        $package_label = !empty($p['label']) ? $p['label'] : $pkg_id;
                        $package_price = '$' . number_format_i18n((float)$p['price'], 2);
                        break;
                    }
                }
            }
        }

        $pkg_meta = class_exists('Bubbles_Packages') ? Bubbles_Packages::get($pkg_id) : null;
        $pkg_desc = $pkg_meta['description'] ?? '';

        $catalog = apply_filters('bubbles_addons_catalog', array(
            array('slug'=>'heavy_pet_hair','name'=>'Heavy Pet Hair Removal'),
            array('slug'=>'light_pet_hair','name'=>'Light Pet Hair Removal'),
            array('slug'=>'car_baby_seat','name'=>'Car Baby Seat'),
        ));
        $addons_map = array();
        foreach ($catalog as $it) {
            if (!empty($it['slug'])) {
                $addons_map[$it['slug']] = $it['name'];
            }
        }

        $wizard = $this;

        ob_start();
        include plugin_dir_path(__FILE__) . '../templates/wizard-step-confirm.php';
        return ob_get_clean();
    }

    /* ------------------------- SUBMIT FINAL ------------------------- */

    private function handle_final_submit() {
        // Asegurarnos de que WooCommerce está activo
        if (!function_exists('WC')) {
            return '<div class="bb-wizard bb-layout"><div class="bb-panel bb-confirmation"><h3>Booking error</h3><p>WooCommerce is not active.</p></div></div>';
        }

        // ID del producto "Car Detailing Booking"
        $product_id = 240; // 👈 tu producto virtual

        // Datos principales del formulario
        $car_year  = $this->posted('car_year');
        $car_make  = $this->posted('car_make');
        $car_model = $this->posted('car_model');

        $pkg_id    = $this->posted('bb_package');
        $addons    = $this->posted_array('addons');

        $address_type  = $this->posted('bb_address_type');
        $address       = $this->posted('bb_address');
        $address_extra = $this->posted('bb_address_extra');
        $place_id      = $this->posted('bb_place_id');

        $addr_street = $this->posted('bb_address_street');
        $addr_city   = $this->posted('bb_address_city');
        $addr_state  = $this->posted('bb_address_state');
        $addr_zip    = $this->posted('bb_address_zip');
        $addr_lat    = $this->posted('bb_address_lat');
        $addr_lng    = $this->posted('bb_address_lng');

        $bb_date = $this->posted('bb_date');
        $bb_time = $this->posted('bb_time');

        $bb_name  = $this->posted('bb_name');
        $bb_phone = $this->posted('bb_phone');
        $bb_email = $this->posted('bb_email');
        $bb_notes = isset($_POST['bb_notes']) ? wp_kses_post($_POST['bb_notes']) : '';

        /* ---- 1) Calcular precio base del paquete ---- */
        $base_price = null;

        $vehicle_for_price = array(
            'year'  => $car_year,
            'make'  => $car_make,
            'model' => $car_model,
        );

        if (function_exists('bb_custom_price_quote') && $pkg_id) {
            $quote = bb_custom_price_quote($vehicle_for_price);
            if (is_array($quote)) {
                foreach ($quote as $p) {
                    if (!empty($p['id']) && $p['id'] === $pkg_id && isset($p['price'])) {
                        $base_price = (float) $p['price'];
                        break;
                    }
                }
            }
        }

        /* ---- 2) Calcular precio de add-ons ---- */
        $addons_total = 0.0;
        if (!empty($addons)) {
            $addons_catalog = apply_filters('bubbles_addons_catalog', array(
                array(
                    'slug'  => 'heavy_pet_hair',
                    'name'  => 'Heavy Pet Hair Removal',
                    'desc'  => 'Intensive pet-hair removal from seats, carpets, and hard-to-reach areas.',
                    'price' => 45,
                ),
                array(
                    'slug'  => 'light_pet_hair',
                    'name'  => 'Light Pet Hair Removal',
                    'desc'  => 'Light pet-hair removal in visible areas.',
                    'price' => 25,
                ),
                array(
                    'slug'  => 'car_baby_seat',
                    'name'  => 'Car Baby Seat',
                    'desc'  => 'Detailed cleaning of the child car seat (accessible areas).',
                    'price' => 25,
                ),
            ));

            $price_map = array();
            foreach ($addons_catalog as $ad) {
                if (!empty($ad['slug'])) {
                    $price_map[$ad['slug']] = isset($ad['price']) ? (float)$ad['price'] : 0.0;
                }
            }

            foreach ($addons as $slug) {
                if (isset($price_map[$slug])) {
                    $addons_total += $price_map[$slug];
                }
            }
        }

        /* ---- 3) Precio final ---- */
        $final_price = null;
        if ($base_price !== null) {
            $final_price = $base_price + $addons_total;
        } elseif ($addons_total > 0) {
            $final_price = $addons_total;
        }

        /* ---- 4) (Opcional) Crear registro interno en Bubbles_Bookings ---- */
        if (class_exists('Bubbles_Bookings')) {
            $data = array(
                'customer_name'   => $bb_name,
                'customer_phone'  => $bb_phone,
                'customer_email'  => $bb_email,
                'vehicle_year'    => $car_year,
                'vehicle_make'    => $car_make,
                'vehicle_model'   => $car_model,
                'package'         => $pkg_id,
                'date'            => $bb_date,
                'time'            => $bb_time,
                'status'          => 'pending',
                'notes'           => $bb_notes,
                'address_type'    => $address_type,
                'address'         => $address,
                'address_extra'   => $address_extra,
            );
            Bubbles_Bookings::create($data);
        }

        /* ---- 5) Vaciar carrito y añadir el producto 240 ---- */
        WC()->cart->empty_cart();

        $cart_item_data = array(
            'car_year'          => $car_year,
            'car_make'          => $car_make,
            'car_model'         => $car_model,
            'package'           => $pkg_id,
            'addons'            => $addons,
            'date'              => $bb_date,
            'time'              => $bb_time,
            'name'              => $bb_name,
            'phone'             => $bb_phone,
            'email'             => $bb_email,
            'notes'             => $bb_notes,
            'address_type'      => $address_type,
            'address'           => $address,
            'address_extra'     => $address_extra,
            'bb_place_id'       => $place_id,
            'bb_address_street' => $addr_street,
            'bb_address_city'   => $addr_city,
            'bb_address_state'  => $addr_state,
            'bb_address_zip'    => $addr_zip,
            'bb_address_lat'    => $addr_lat,
            'bb_address_lng'    => $addr_lng,
        );

        if ($final_price !== null) {
            $cart_item_data['custom_price'] = $final_price;
        }

        $added = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

        if (!$added) {
            return '<div class="bb-wizard bb-layout"><div class="bb-panel bb-confirmation"><h3>Booking error</h3><p>We could not add the booking to your cart. Please try again.</p></div></div>';
        }

        /* ---- 6) Redirigir a checkout ---- */
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
}

// Bootstrap
new Bubbles_Wizard();

} // fin if !class_exists

// Guardar los datos del booking en la línea del pedido
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values) {
    $fields = array(
        'car_year','car_make','car_model',
        'package','addons',
        'date','time',
        'name','phone','email','notes',
        'address_type','address','address_extra',
        'bb_place_id','bb_address_street','bb_address_city',
        'bb_address_state','bb_address_zip',
        'bb_address_lat','bb_address_lng'
    );

    foreach ($fields as $f) {
        if (isset($values[$f]) && $values[$f] !== '') {
            $value = $values[$f];
            if (is_array($value)) {
                $value = implode(', ', array_map('sanitize_text_field', $value));
            }
            $item->add_meta_data(ucfirst(str_replace('_',' ',$f)), $value);
        }
    }
}, 10, 3);

// Aplicar custom_price al carrito (precio dinámico)
add_filter('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    if (empty($cart) || !is_a($cart, 'WC_Cart')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item_key => $item) {
        if (isset($item['custom_price']) && is_numeric($item['custom_price']) && $item['custom_price'] > 0) {
            $item['data']->set_price($item['custom_price']);
        }
    }
});
