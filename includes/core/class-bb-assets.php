<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Assets {

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front' ), 5 );
        // Si más adelante quieres algo en el admin, usamos admin_enqueue_scripts
        // add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
    }

    /**
     * FRONTEND (wizard + tech dashboard)
     */
    public static function enqueue_front() {
        if ( is_admin() ) {
            return;
        }

        global $post;

        if ( ! ( is_singular() && is_a( $post, 'WP_Post' ) ) ) {
            return;
        }

        $content     = $post->post_content;
        $has_wizard  = has_shortcode( $content, 'bubbles_wizard' );
        $has_tech    = has_shortcode( $content, 'bb_staff_dashboard' );

        // Si la página no tiene ninguno de los dos shortcodes, no cargamos nada
        if ( ! $has_wizard && ! $has_tech ) {
            return;
        }

        // Rutas de archivos
        $picker_js    = BB_PLUGIN_DIR . 'assets/js/bb-picker.js';
        $wizard_js    = BB_PLUGIN_DIR . 'assets/js/bb-wizard.js';
        $wizard_css   = BB_PLUGIN_DIR . 'assets/css/bb-wizard.css';
        $picker_css   = BB_PLUGIN_DIR . 'assets/css/bb-picker.css';
        $thankyou_css = BB_PLUGIN_DIR . 'assets/css/bb-thankyou.css';
        $tech_css     = BB_PLUGIN_DIR . 'assets/css/tech/tech.css';

        // =========================
        //  WIZARD (bubbles_wizard)
        // =========================
        if ( $has_wizard ) {

            // ---------- JS básicos ----------
            // Picker (selector de vehículos)
            if ( file_exists( $picker_js ) ) {
                wp_enqueue_script(
                    'bb-picker',
                    BB_PLUGIN_URL . 'assets/js/bb-picker.js',
                    array(),
                    filemtime( $picker_js ),
                    true
                );
            }

            // Wizard helper (lógica de pasos / UX)
            if ( file_exists( $wizard_js ) ) {
                wp_enqueue_script(
                    'bb-wizard',
                    BB_PLUGIN_URL . 'assets/js/bb-wizard.js',
                    array( 'bb-picker' ),
                    filemtime( $wizard_js ),
                    true
                );
            }

            // ---------- CSS ----------
            if ( file_exists( $wizard_css ) ) {
                wp_enqueue_style(
                    'bb-wizard',
                    BB_PLUGIN_URL . 'assets/css/bb-wizard.css',
                    array(),
                    filemtime( $wizard_css )
                );
            }

            if ( file_exists( $picker_css ) ) {
                wp_enqueue_style(
                    'bb-picker-css',
                    BB_PLUGIN_URL . 'assets/css/bb-picker.css',
                    array( 'bb-wizard' ),
                    filemtime( $picker_css )
                );
            }

            if ( file_exists( $thankyou_css ) ) {
                wp_enqueue_style(
                    'bb-thankyou-css',
                    BB_PLUGIN_URL . 'assets/css/bb-thankyou.css',
                    array( 'bb-wizard' ),
                    filemtime( $thankyou_css )
                );
            }

            // ---------- Stripe Elements (Step 6) ----------
            if ( function_exists( 'bubbles_booking_get_options' ) ) {

                $options   = bubbles_booking_get_options();
                $stripe_pk = isset( $options['stripe_public_key'] ) ? trim( $options['stripe_public_key'] ) : '';

                if ( ! empty( $stripe_pk ) ) {

                    // Stripe.js oficial
                    wp_enqueue_script(
                        'stripe-js',
                        'https://js.stripe.com/v3/',
                        array(),
                        null,
                        true
                    );

                    // Nuestro JS de integración con Stripe Elements
                    $bb_stripe_js = BB_PLUGIN_DIR . 'assets/js/bb-stripe.js';

                    if ( file_exists( $bb_stripe_js ) ) {
                        wp_enqueue_script(
                            'bb-stripe',
                            BB_PLUGIN_URL . 'assets/js/bb-stripe.js',
                            array( 'stripe-js' ),
                            filemtime( $bb_stripe_js ),
                            true
                        );

                        wp_localize_script(
                            'bb-stripe',
                            'BBStripeData',
                            array(
                                'publishableKey' => $stripe_pk,
                                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                                'currency'       => 'usd',
                            )
                        );
                    }
                }
            }

            // ---------- Google Places (autocomplete address) ----------
            // (esto era lo que hacías en bubbles_booking_enqueue_address_assets)
            $options = get_option( 'bubbles_booking_options', array() );
            $api_key = isset( $options['google_maps_api_key'] ) ? trim( $options['google_maps_api_key'] ) : '';
            $api_key = apply_filters( 'bubbles_google_maps_api_key', $api_key );

            if ( ! empty( $api_key ) ) {
                wp_enqueue_script(
                    'bubbles-google-places',
                    'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $api_key ) . '&libraries=places',
                    array(),
                    null,
                    true
                );

                $addr_js = BB_PLUGIN_DIR . 'assets/js/bb-address-autocomplete.js';
                if ( file_exists( $addr_js ) ) {
                    wp_enqueue_script(
                        'bubbles-address-autocomplete',
                        BB_PLUGIN_URL . 'assets/js/bb-address-autocomplete.js',
                        array( 'bubbles-google-places' ),
                        filemtime( $addr_js ),
                        true
                    );
                }
            }
        }

        
    }

    public static function enqueue_admin( $hook ) {
        // Aquí más adelante puedes cargar CSS/JS solo para la página de settings
        // if ( $hook === 'toplevel_page_bubbles-booking-settings' ) { ... }
    }
}
