<?php
if ( ! function_exists('bb_google_places_key') ) {
  function bb_google_places_key() : string {
    $opts = bubbles_booking_get_options();
    return trim((string)($opts['google_places_key'] ?? ''));
  }
}
