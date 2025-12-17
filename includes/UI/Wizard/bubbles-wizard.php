<?php
if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! class_exists( 'Bubbles_Wizard' ) ) {

class Bubbles_Wizard {

    /** @var string[] */
    protected $steps = array( 'vehicle', 'package', 'addons', 'address', 'date', 'confirm', 'payment' );

    public function __construct() {
        $this->includes();

        add_shortcode( 'bubbles_wizard', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Includes base del wizard (handlers PHP, AJAX endpoints)
     */
    private function includes(): void {

        // AJAX proxy para vehicle picker (CORS-safe)
        $ajax = BB_PLUGIN_DIR . 'includes/UI/Wizard/ajax/bb-vehicle-ajax.php';
        if ( file_exists( $ajax ) ) {
            require_once $ajax;
        }
    }

    /**
     * Encola JS/CSS del wizard
     */
    public function enqueue_assets() {

        if ( is_admin() ) return;
        if ( ! is_singular() ) return;

        global $post;
        if ( ! ( $post instanceof WP_Post ) ) return;

        // Solo si la página tiene el shortcode
        if ( ! has_shortcode( $post->post_content, 'bubbles_wizard' ) ) {
            return;
        }

        $ver = defined('BB_VERSION') ? BB_VERSION : '1.0.0';

        // === CSS ===
        wp_enqueue_style( 'bb-picker', BB_PLUGIN_URL . 'assets/css/wizard/bb-picker.css', array(), $ver );
        wp_enqueue_style( 'bb-date',   BB_PLUGIN_URL . 'assets/css/wizard/bb-date.css',   array(), $ver );
        wp_enqueue_style( 'bb-wizard', BB_PLUGIN_URL . 'assets/css/wizard/bb-wizard.css', array(), $ver );

        // === JS: Vehicle picker ===
        wp_enqueue_script('bb-vehicle-picker',BB_PLUGIN_URL . 'assets/js/wizard/bb-picker.js',
            array(),
            $ver,
            true
        );

        wp_localize_script('bb-vehicle-picker','BBPickerData', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),'nonce'   => wp_create_nonce( 'bb_picker_nonce' ),
            )
        );

        /**
         * ===== Google Places + Address Autocomplete =====
         */
        $places_key = function_exists('bb_google_places_key') ? bb_google_places_key() : '';
        $addr_deps  = array();

        if ( $places_key !== '' ) {
            // Evitar doble carga por si otro plugin/tema lo mete
            if ( ! wp_script_is( 'bb-google-places', 'enqueued' ) && ! wp_script_is( 'bb-google-places', 'done' ) ) {
               wp_enqueue_script(
  'bb-google-places',
  'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($places_key) . '&libraries=places&loading=async',
  array(),
  null,
  true
);

            }
            $addr_deps[] = 'bb-google-places';
        }

        // ✅ Encolar SOLO una vez el autocomplete
        wp_enqueue_script('bb-address-autocomplete',BB_PLUGIN_URL . 'assets/js/wizard/bb-address-autocomplete.js',
            $addr_deps,$ver, true );

       /**
 * ===== Stripe =====
 */

// 1) Registra stripe-v3 una sola vez (no duplica si ya existe)
if ( ! wp_script_is( 'stripe-v3', 'registered' ) ) {
    wp_register_script('stripe-v3','https://js.stripe.com/v3/',
        array(), null, true
   );
}

// 2) Encola stripe-v3 SOLO si no está ya en cola ni ya salió
if ( ! wp_script_is( 'stripe-v3', 'enqueued' ) && ! wp_script_is( 'stripe-v3', 'done' ) ) {
   wp_enqueue_script( 'stripe-v3' );
}

// 3) Encola tu script SOLO si no está en cola (evita doble bb-stripe)
if ( ! wp_script_is( 'bb-stripe', 'enqueued' ) && ! wp_script_is( 'bb-stripe', 'done' ) ) {
    wp_enqueue_script('bb-stripe', BB_PLUGIN_URL . 'assets/js/wizard/bb-stripe.js',
       array('stripe-v3'),
       $ver,
       true
    );
}

// 4) Localize SOLO una vez
if ( ! wp_script_is( 'bb-stripe', 'done' ) ) {

    $pk = function_exists('bb_stripe_public_key') ? bb_stripe_public_key() : '';

    // Evita duplicar la variable si alguien ya la imprimió
    if ( ! isset( $GLOBALS['bb_stripe_data_localized'] ) ) {
        $GLOBALS['bb_stripe_data_localized'] = true;

        wp_localize_script('bb-stripe', 'BBStripeData', array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'publishableKey' => $pk,
            'currency'       => 'usd',
            'nonce'          => wp_create_nonce('bb_create_pi'),
        ));
    }
}

    }

    public function get_steps() {
        return $this->steps;
    }

    public function render() {

        $state = $this->get_current_state();

        if ( ! class_exists( 'BB_Wizard_Shell' ) ) {
            require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-wizard-shell.php';
        }

        $shell = new BB_Wizard_Shell( $state );
        $html  = $shell->handle_request();

        $this->save_state( $shell->get_state() );

        return $html;
    }

    public function get_current_state() {
        return array();
    }

    public function save_state( array $state ) {
        // TODO
    }
}

} // end class_exists
