<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Controlador de confirmación y (en el futuro) pagos
 *
 * - Se llama desde:
 *   - Bubbles_Wizard::render() al hacer POST con bb_confirm_booking
 *   - (Opcional) Bubbles_Wizard::render_step_confirm() para calcular el total
 *
 * Versión actual:
 *   - NO usa Stripe todavía.
 *   - Calcula el precio final (basado en vehículo actual).
 *   - Crea la reserva interna (Bubbles_Bookings) si la clase existe.
 *   - Muestra una pantalla de "Thank you" dentro de tu página.
 *
 * Más adelante aquí podemos conectar Stripe (inline o checkout).
 */

if ( ! class_exists('Bubbles_Confirm_Checkout') ) {

class Bubbles_Confirm_Checkout {

    /**
     * Calcula el total (paquete + add-ons) leyendo directamente $_POST.
     *
     * Devuelve array:
     *   [
     *     'base'          => (float|null) precio paquete,
     *     'addons_total'  => (float) total add-ons,
     *     'total'         => (float|null) suma,
     *     'package_label' => (string) nombre del paquete
     *   ]
     *
     * Si no se puede calcular, 'total' será null.
     */
    public static function calculate_total_from_post() {

        $car_year  = isset($_POST['car_year'])  ? sanitize_text_field($_POST['car_year'])  : '';
        $car_make  = isset($_POST['car_make'])  ? sanitize_text_field($_POST['car_make'])  : '';
        $car_model = isset($_POST['car_model']) ? sanitize_text_field($_POST['car_model']) : '';

        $pkg_id = isset($_POST['bb_package']) ? sanitize_text_field($_POST['bb_package']) : '';

        $addons = array();
        if ( isset($_POST['addons']) && is_array($_POST['addons']) ) {
            $addons = array_map('sanitize_text_field', $_POST['addons']);
        }

        $vehicle_for_price = array(
            'year'  => $car_year,
            'make'  => $car_make,
            'model' => $car_model,
        );

        $base_price    = null;
        $package_label = $pkg_id;

        // 1) Precio base del paquete (usando tu función de precios)
        if ( function_exists('bb_custom_price_quote') && $pkg_id ) {
            $quote = bb_custom_price_quote( $vehicle_for_price );
            if ( is_array($quote) ) {
                foreach ( $quote as $p ) {
                    if ( ! empty($p['id']) && $p['id'] === $pkg_id ) {
                        if ( isset($p['price']) ) {
                            $base_price = (float) $p['price'];
                        }
                        if ( ! empty($p['label']) ) {
                            $package_label = $p['label'];
                        }
                        break;
                    }
                }
            }
        }

        // 2) Precio de add-ons
        $addons_total = 0.0;
        if ( ! empty($addons) ) {
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
            foreach ( $addons_catalog as $ad ) {
                if ( ! empty($ad['slug']) ) {
                    $price_map[ $ad['slug'] ] = isset($ad['price']) ? (float) $ad['price'] : 0.0;
                }
            }

            foreach ( $addons as $slug ) {
                if ( isset($price_map[$slug]) ) {
                    $addons_total += $price_map[$slug];
                }
            }
        }

        // 3) Total
        $total = null;
        if ( $base_price !== null ) {
            $total = $base_price + $addons_total;
        } elseif ( $addons_total > 0 ) {
            $total = $addons_total;
        }

        return array(
            'base'          => $base_price,
            'addons_total'  => $addons_total,
            'total'         => $total,
            'package_label' => $package_label,
        );
    }

    /**
     * Helper de compatibilidad para plantillas que llamen get_final_totals().
     *
     * Devuelve:
     *   [
     *     'base_price'    => float|null,
     *     'addons_total'  => float,
     *     'final_price'   => float|null,
     *     'package_label' => string,
     *   ]
     */
    public static function get_final_totals() {
        $calc = self::calculate_total_from_post();

        return array(
            'base_price'    => $calc['base'],
            'addons_total'  => $calc['addons_total'],
            'final_price'   => $calc['total'],
            'package_label' => $calc['package_label'],
        );
    }

    /**
     * Punto de entrada cuando el usuario pulsa "Confirm & pay"
     * en el Step 6 del wizard.
     *
     * Versión actual:
     *  - NO conecta con Stripe.
     *  - Calcula precio final.
     *  - Crea reserva interna (si existe Bubbles_Bookings).
     *  - Muestra pantalla de "Thank you" dentro del sitio.
     */
    public static function handle_booking_submit() {

        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return self::render_error_box('Invalid request method.');
        }

        // === 1) Leer datos de contacto y servicio ===
        $bb_name  = isset($_POST['bb_name'])  ? sanitize_text_field($_POST['bb_name'])  : '';
        $bb_phone = isset($_POST['bb_phone']) ? sanitize_text_field($_POST['bb_phone']) : '';
        $bb_email = isset($_POST['bb_email']) ? sanitize_email($_POST['bb_email'])      : '';

        $car_year  = isset($_POST['car_year'])  ? sanitize_text_field($_POST['car_year'])  : '';
        $car_make  = isset($_POST['car_make'])  ? sanitize_text_field($_POST['car_make'])  : '';
        $car_model = isset($_POST['car_model']) ? sanitize_text_field($_POST['car_model']) : '';

        $pkg_id = isset($_POST['bb_package']) ? sanitize_text_field($_POST['bb_package']) : '';

        $addons = array();
        if ( isset($_POST['addons']) && is_array($_POST['addons']) ) {
            $addons = array_map('sanitize_text_field', $_POST['addons']);
        }

        $address_type  = isset($_POST['bb_address_type'])  ? sanitize_text_field($_POST['bb_address_type'])  : '';
        $address       = isset($_POST['bb_address'])       ? sanitize_text_field($_POST['bb_address'])       : '';
        $address_extra = isset($_POST['bb_address_extra']) ? sanitize_text_field($_POST['bb_address_extra']) : '';

        $bb_date = isset($_POST['bb_date']) ? sanitize_text_field($_POST['bb_date']) : '';
        $bb_time = isset($_POST['bb_time']) ? sanitize_text_field($_POST['bb_time']) : '';

        $bb_notes = isset($_POST['bb_notes'])
            ? wp_kses_post($_POST['bb_notes'])
            : '';

        // Validaciones mínimas
        if ( empty($bb_name) || empty($bb_phone) || empty($bb_email) ) {
            return self::render_error_box(
                'Booking error<br>Please fill in name, phone and email.'
            );
        }

        if ( empty($pkg_id) ) {
            return self::render_error_box(
                'Booking error<br>No service package was selected.'
            );
        }

        // === 2) Calcular precio usando el helper ===
        $calc          = self::calculate_total_from_post();
        $final_price   = $calc['total'];
        $package_label = $calc['package_label'];

        if ( $final_price === null ) {
            return self::render_error_box(
                'Booking error<br>Could not calculate final price.'
            );
        }

        // === 3) Crear registro interno en Bubbles_Bookings (opcional) ===
        if ( class_exists('Bubbles_Bookings') ) {
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
                'status'          => 'pending', // pendiente de confirmar / cobrar
                'notes'           => $bb_notes,
                'address_type'    => $address_type,
                'address'         => $address,
                'address_extra'   => $address_extra,
                'total_price'     => (float) $final_price,
            );

            Bubbles_Bookings::create( $data );
        }

        // === 4) Preparar state global (resumen) usando el Wizard (puente con Bubbles_Summary) ===
        $state  = array();
        $wizard = null;

        if ( class_exists('Bubbles_Wizard') ) {
            // Creamos una instancia SOLO para usar get_current_state()
            $wizard = new Bubbles_Wizard();
            if ( method_exists( $wizard, 'get_current_state' ) ) {
                $state = $wizard->get_current_state();
            }
        }

        // === 5) Pantalla final de "gracias" dentro del plugin ===
        ob_start();

        $bb_final_price = (float) $final_price;

        $view_data = array(
            'bb_name'       => $bb_name,
            'bb_phone'      => $bb_phone,
            'bb_email'      => $bb_email,
            'car_year'      => $car_year,
            'car_make'      => $car_make,
            'car_model'     => $car_model,
            'package_label' => $package_label,
            'bb_date'       => $bb_date,
            'bb_time'       => $bb_time,
            'address'       => $address,
            'address_extra' => $address_extra,
            'final_price'   => $bb_final_price,
            'wizard'        => $wizard,
            'state'         => $state,
        );

        // Extrae variables de $view_data sin machacar las que ya existan
        extract( $view_data, EXTR_SKIP );

        $thankyou_tpl = BB_PLUGIN_DIR . 'templates/wizard/wizard-checkout-thankyou.php';

        if ( file_exists( $thankyou_tpl ) ) {
            include $thankyou_tpl;
        } else {
            // fallback simple si faltara la plantilla
            ?>
            <div class="bb-wizard bb-layout">
                <div class="bb-panel bb-confirmation">
                    <h3><?php esc_html_e('Thank you! Your booking was received.', 'bubbles-booking'); ?></h3>
                    <p><strong><?php esc_html_e('Total:', 'bubbles-booking'); ?></strong>
                        <?php echo esc_html( '$' . number_format_i18n( (float) $bb_final_price, 2 ) ); ?>
                    </p>
                </div>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Retorno desde Stripe (placeholder).
     * Por ahora NO usamos Stripe, así que siempre devuelve string vacío.
     */
    public static function maybe_render_stripe_return() {
        return '';
    }

    /**
     * Helper para renderizar un cuadro de error dentro del layout del wizard.
     */
    protected static function render_error_box( $message ) {
        ob_start();
        ?>
        <div class="bb-wizard bb-layout">
            <div class="bb-panel bb-confirmation">
                <h3><?php esc_html_e('Payment / booking error', 'bubbles-booking'); ?></h3>
                <p><?php echo wp_kses_post( $message ); ?></p>
                <p style="margin-top:1rem;">
                    <a href="<?php echo esc_url( wp_get_referer() ? wp_get_referer() : home_url('/') ); ?>"
                       class="bb-btn bb-btn-secondary">
                        <?php esc_html_e('Back', 'bubbles-booking'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

} // end if !class_exists
