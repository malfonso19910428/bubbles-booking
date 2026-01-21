<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Integrations Helpers — Bubbles Booking
 *
 * Este archivo contiene helpers de integraciones (Stripe + Google Maps/Places).
 * ⚠️ Fuentes de verdad:
 *  - Stripe: option bubbles_booking_options
 *  - Google Maps/Places: option bubbles_booking_map_settings
 */

/**
 * Devuelve el array completo de opciones del plugin (Stripe, etc.)
 * option_name = bubbles_booking_options
 */
if ( ! function_exists('bubbles_booking_get_options') ) {
    function bubbles_booking_get_options() : array {

        $opts = get_option( 'bubbles_booking_options', array() );

        if ( ! is_array( $opts ) ) {
            $opts = array();
        }

        return $opts;
    }
}

/**
 * Modo: test | live
 */
if ( ! function_exists('bb_stripe_mode') ) {
    function bb_stripe_mode() : string {

        $opts = bubbles_booking_get_options();
        $m    = isset($opts['stripe_mode']) ? (string)$opts['stripe_mode'] : 'test';

        return ($m === 'live') ? 'live' : 'test';
    }
}

/**
 * Currency ISO-4217 (3 letras). Default: usd
 */
if ( ! function_exists('bb_stripe_currency') ) {
    function bb_stripe_currency() : string {

        $opts = bubbles_booking_get_options();
        $cur  = isset($opts['stripe_currency']) ? (string)$opts['stripe_currency'] : 'usd';

        $cur = strtolower(trim($cur));
        $cur = preg_replace('/[^a-z]/', '', $cur);

        return (strlen($cur) === 3) ? $cur : 'usd';
    }
}

/**
 * Stripe Publishable Key (pk_test / pk_live)
 */
if ( ! function_exists('bb_stripe_public_key') ) {
    function bb_stripe_public_key() : string {

        $opts = bubbles_booking_get_options();
        $key  = isset($opts['stripe_public_key']) ? (string)$opts['stripe_public_key'] : '';

        return trim($key);
    }
}

/**
 * Stripe Secret Key (sk_test / sk_live)
 * ⚠️ SOLO backend
 */
if ( ! function_exists('bb_stripe_secret_key') ) {
    function bb_stripe_secret_key() : string {

        $opts = bubbles_booking_get_options();
        $key  = isset($opts['stripe_secret_key']) ? (string)$opts['stripe_secret_key'] : '';

        return trim($key);
    }
}

/**
 * Verifica si Stripe está configurado
 */
if ( ! function_exists('bb_stripe_is_configured') ) {
    function bb_stripe_is_configured() : bool {

        return (bb_stripe_public_key() !== '' && bb_stripe_secret_key() !== '');
    }
}

/**
 * Verifica que las keys correspondan con el modo (test/live)
 * Evita pk_test con sk_live, etc.
 */
if ( ! function_exists('bb_stripe_keys_match_mode') ) {
    function bb_stripe_keys_match_mode() : bool {

        $mode = bb_stripe_mode();
        $pk   = bb_stripe_public_key();
        $sk   = bb_stripe_secret_key();

        if ( $pk === '' || $sk === '' ) return false;

        if ( $mode === 'test' ) {
            return (strpos($pk, 'pk_test_') === 0) && (strpos($sk, 'sk_test_') === 0);
        }

        // live
        return (strpos($pk, 'pk_live_') === 0) && (strpos($sk, 'sk_live_') === 0);
    }
}

/**
 * Google Places / Maps API Key
 * option_name = bubbles_booking_map_settings
 * index       = google_maps_api_key
 */
if ( ! function_exists('bb_google_places_key') ) {
    function bb_google_places_key() : string {

        $opts = get_option('bubbles_booking_map_settings', array());
        if ( ! is_array($opts) ) $opts = array();

        return trim( (string) ($opts['google_maps_api_key'] ?? '') );
    }
}

/**
 * Google Places Loader (genérico)
 * - Evita doble carga aunque Elementor/tema ya lo haya inyectado
 * - Exponer: window.__bbGooglePlacesReady(cb)
 *
 * Uso:
 *   wp_enqueue_script('bb-address-autocomplete', ... );
 *   bb_google_places_loader('bb-address-autocomplete');
 */
if ( ! function_exists('bb_google_places_loader') ) {

    function bb_google_places_loader(string $attach_handle): void {

        $key = bb_google_places_key();
        if ( $key === '' ) return;

        // Asegura que el handle exista para poder pegar inline.
        // Fallback: jquery (suele estar)
        if ( ! wp_script_is($attach_handle, 'enqueued') && ! wp_script_is($attach_handle, 'done') ) {
            if ( wp_script_is('jquery', 'enqueued') || wp_script_is('jquery', 'done') ) {
                $attach_handle = 'jquery';
            } else {
                // último recurso: imprimir en footer
                add_action('wp_footer', function() use ($key) {
                    echo '<script>' . bb_google_places_loader_js($key) . '</script>';
                }, 100);
                return;
            }
        }

        // “before” para que el loader corra antes del JS que inicializa autocomplete
        wp_add_inline_script($attach_handle, bb_google_places_loader_js($key), 'before');
    }

    function bb_google_places_loader_js(string $key): string {

        $k = esc_js($key);

        return <<<JS
(function(){
  try {
    // Helper global: ejecuta cb cuando Places esté listo
    window.__bbGooglePlacesReady = window.__bbGooglePlacesReady || function(cb){
      if (window.google && google.maps && google.maps.places) { try{ cb(); }catch(e){} return; }
      window.__bbGooglePlacesQueue = window.__bbGooglePlacesQueue || [];
      window.__bbGooglePlacesQueue.push(cb);
    };

    function flush(){
      var q = window.__bbGooglePlacesQueue || [];
      window.__bbGooglePlacesQueue = [];
      q.forEach(function(fn){ try{ fn(); }catch(e){} });
    }

    // Si ya existe (Elementor/tema lo cargó), listo
    if (window.google && google.maps && google.maps.places) { flush(); return; }

    // Evita doble intento del loader BB
    if (window.__bbGooglePlacesLoading) return;
    window.__bbGooglePlacesLoading = true;

    // Si ya hay un script de maps en el DOM, solo espera
    var already = Array.from(document.scripts || []).some(function(s){
      return (s.src || '').indexOf('maps.googleapis.com') !== -1;
    });

    if (already) {
      var tries = 80;
      (function wait(){
        if (window.google && google.maps && google.maps.places) { flush(); return; }
        if (tries-- <= 0) { console.warn('[BB] Google Places not ready (timeout)'); return; }
        setTimeout(wait, 150);
      })();
      return;
    }

    // Cargar Maps + Places
    var s = document.createElement('script');
    s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent('{$k}') + '&libraries=places';
    s.async = true;
    s.onload = function(){ flush(); };
    s.onerror = function(){ console.warn('[BB] Failed to load Google Maps JS'); };
    document.head.appendChild(s);

  } catch(e) {
    console.warn('[BB] loader exception', e);
  }
})();
JS;
    }
}
