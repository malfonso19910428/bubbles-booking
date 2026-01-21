<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Bubbles_Wizard' ) ) {

class Bubbles_Wizard {

    /** @var string[] */
    protected $steps = array( 'vehicle', 'package', 'addons', 'address', 'date', 'tech', 'customer', 'payment' );

    /** @var string */
    private string $picker_handle = 'bb-picker';

    public function __construct() {
        $this->includes();

        add_shortcode( 'bubbles_wizard', array( $this, 'render' ) );

        // Importante: deja que Elementor/tema registren cosas primero
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
    }

    /**
     * Includes base del wizard (AJAX endpoints)
     */
    private function includes(): void {

        $ajax1 = BB_PLUGIN_DIR . 'includes/UI/Wizard/ajax/bb-vehicle-ajax.php';
        if ( file_exists( $ajax1 ) ) require_once $ajax1;

        $ajax2 = BB_PLUGIN_DIR . 'includes/UI/Wizard/ajax/bb-address-coverage-ajax.php';
        if ( file_exists( $ajax2 ) ) require_once $ajax2;

        $ajax3 = BB_PLUGIN_DIR . 'includes/UI/Wizard/ajax/bb-wizard-slots-ajax.php';
        if ( file_exists( $ajax3 ) ) require_once $ajax3;

        // ✅ Finalize booking AFTER payment
        $ajax4 = BB_PLUGIN_DIR . 'includes/UI/Wizard/ajax/bb-finalize-booking-ajax.php';
        if ( file_exists( $ajax4 ) ) require_once $ajax4;
    }

    private function should_enqueue_assets(): bool {

        if ( is_admin() ) return false;

        // Caso Elementor: el wizard vive en /pricing
        if ( is_page( 'pricing' ) ) return true;

        // Caso normal: página/post con shortcode
        global $post;
        if ( $post instanceof WP_Post ) {
            if ( has_shortcode( (string) $post->post_content, 'bubbles_wizard' ) ) return true;
        }

        return false;
    }

    public function enqueue_assets(): void {

        if ( ! $this->should_enqueue_assets() ) return;

        $ver = defined( 'BB_VERSION' ) ? BB_VERSION : '1.0.0';

        /**
         * Helper: version por filemtime (cache bust)
         */
        $bb_css_ver = function( $rel_path ) use ( $ver ) {
            $abs = BB_PLUGIN_DIR . ltrim( $rel_path, '/' );
            return file_exists( $abs ) ? (string) filemtime( $abs ) : $ver;
        };

        // ===== CSS =====
        wp_enqueue_style( 'bb-picker', BB_PLUGIN_URL . 'assets/css/wizard/bb-picker.css', array(), $bb_css_ver('assets/css/wizard/bb-picker.css') );
        wp_enqueue_style( 'bb-date',   BB_PLUGIN_URL . 'assets/css/wizard/bb-date.css',   array(), $bb_css_ver('assets/css/wizard/bb-date.css') );
        wp_enqueue_style( 'bb-wizard', BB_PLUGIN_URL . 'assets/css/wizard/bb-wizard.css', array(), $bb_css_ver('assets/css/wizard/bb-wizard.css') );

        wp_enqueue_style( 'bb-flatpickr', BB_PLUGIN_URL . 'assets/css/wizard/flatpickr.min.css', array(), '4.6.13' );

        // ===== JS: Flatpickr =====
        $fp_path = BB_PLUGIN_DIR . 'assets/js/wizard/flatpickr.min.js';
        $fp_ver  = file_exists( $fp_path ) ? (string) filemtime( $fp_path ) : '4.6.13';

        wp_enqueue_script( 'bb-flatpickr', BB_PLUGIN_URL . 'assets/js/wizard/flatpickr.min.js', array(), $fp_ver, true );

        // ===== JS: Vehicle Picker =====
        $picker_path = BB_PLUGIN_DIR . 'assets/js/wizard/bb-picker.js';
        $picker_ver  = file_exists( $picker_path ) ? (string) filemtime( $picker_path ) : $ver;

        wp_enqueue_script( 'bb-picker', BB_PLUGIN_URL . 'assets/js/wizard/bb-picker.js', array(), $picker_ver, true );

        wp_localize_script( 'bb-picker', 'BBPickerData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bb_picker_nonce' ),
        ) );

        // ===== Address autocomplete =====
        $addr_path = BB_PLUGIN_DIR . 'assets/js/wizard/bb-address-autocomplete.js';
        $addr_ver  = file_exists( $addr_path ) ? (string) filemtime( $addr_path ) : $ver;

        wp_enqueue_script( 'bb-address-autocomplete', BB_PLUGIN_URL . 'assets/js/wizard/bb-address-autocomplete.js', array(), $addr_ver, true );

        wp_localize_script( 'bb-address-autocomplete', 'BBAddressData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bb_address_coverage_nonce' ),
            'action'  => 'bb_wizard_address_coverage',
        ) );

        if ( function_exists( 'bb_google_places_loader' ) ) {
            bb_google_places_loader( 'bb-address-autocomplete' );
        }

        // ===== Date step =====
        $date_path = BB_PLUGIN_DIR . 'assets/js/wizard/bb-date.js';
        $date_ver  = file_exists( $date_path ) ? (string) filemtime( $date_path ) : $ver;

        wp_enqueue_script( 'bb-date-js', BB_PLUGIN_URL . 'assets/js/wizard/bb-date.js', array( 'bb-flatpickr' ), $date_ver, true );

        wp_localize_script( 'bb-date-js', 'BBDateData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'token'   => wp_create_nonce( 'bb_wizard_slots_nonce' ),
            'action'  => 'bb_get_slots',
        ) );

        // ===== Stripe orchestrator (bb-stripe.js) =====
      
$stripe_path = BB_PLUGIN_DIR . 'assets/js/wizard/bb-stripe.js';
$stripe_ver  = file_exists( $stripe_path ) ? (string) filemtime( $stripe_path ) : $ver;

wp_enqueue_script(
    'bb-stripe',
    BB_PLUGIN_URL . 'assets/js/wizard/bb-stripe.js',
    array(),
    $stripe_ver,
    true
);
        // Loader Stripe v3 (guard)
        $stripe_loader = <<<JS
(function(){
  try {
    if (window.Stripe) { return; }
    if (window.__bbStripeLoading) { return; }
    window.__bbStripeLoading = true;
    var s = document.createElement('script');
    s.src = 'https://js.stripe.com/v3/';
    s.async = true;
    document.head.appendChild(s);
  } catch(e) {}
})();
JS;
        wp_add_inline_script( 'bb-stripe', $stripe_loader, 'before' );

        // Localize Stripe SOLO una vez
        if ( ! isset( $GLOBALS['bb_stripe_data_localized'] ) ) {
            $GLOBALS['bb_stripe_data_localized'] = true;

            $pk = function_exists( 'bb_stripe_public_key' ) ? bb_stripe_public_key() : '';

            wp_localize_script( 'bb-stripe', 'BBStripeData', array(
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'publishableKey' => $pk,
                'currency'       => 'usd',
                'nonce'          => wp_create_nonce( 'bb_create_pi' ),
            ) );

            // ✅ Finalize booking (AJAX) después del pago
            wp_localize_script( 'bb-stripe', 'BBFinalizeData', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'action'  => 'bb_finalize_booking',
                'nonce'   => wp_create_nonce( 'bb_finalize_booking_nonce' ),
            ) );
        }
    }

    public function render(): string {

        // ✅ Si venimos de un booking confirmado → mostrar confirmación en el MISMO shortcode
        if ( isset($_GET['bb_booking']) ) {
            $booking_id = absint($_GET['bb_booking']);
            return $this->render_booking_confirmation( $booking_id );
        }

        // Wizard normal
        if ( ! class_exists( 'BB_Wizard_Shell' ) ) {
            require_once BB_PLUGIN_DIR . 'includes/UI/Wizard/bb-wizard-shell.php';
        }

        $shell = new BB_Wizard_Shell();
        return (string) $shell->handle_request();
    }

    /**
     * ✅ Pantalla “Confirmation / Thank you” dentro del mismo shortcode
     * (por ahora muestra ID; luego leemos DB y mostramos resumen completo)
     */
    private function render_booking_confirmation( int $booking_id ): string {

        if ( ! $booking_id ) {
            return '<div class="bb-error">Booking not found.</div>';
        }

        ob_start(); ?>
        <div class="bb-booking-confirmation">
            <h2>Thank you! Your booking is confirmed.</h2>
            <p><strong>Booking ID:</strong> <?php echo esc_html( $booking_id ); ?></p>
            <p>We’ve received your payment and your appointment has been successfully scheduled.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}

} // end class_exists( 'Bubbles_Wizard' )

 // end if ( ! class_exists( 'Bubbles_Wizard' ) )
