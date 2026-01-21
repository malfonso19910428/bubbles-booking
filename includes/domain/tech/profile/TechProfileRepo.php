<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TechProfileRepo
 * - Source of truth: bb_tech_*
 * - Soporta address_formatted (dirección completa)
 * - Backward compat al LEER: bb_profile_* + keys antiguas bb_tech_city/state/zip + bb_tech_lat/lng/place_id + Woo billing_*
 * - ✅ Ya NO escribe duplicado (no más bb_tech_lat/bb_tech_lng/bb_tech_place_id ni bb_tech_city/state/zip)
 */
class TechProfileRepo {

    /**
     * Lee meta primario y si está vacío cae a legacy.
     * Considera vacío: '' o null o false.
     * (Importante: "0" NO es vacío)
     */
    private function read_meta_fallback( int $user_id, string $primary_key, string $legacy_key = '' ) {
        $v = get_user_meta( $user_id, $primary_key, true );

        if ( $v !== '' && $v !== null && $v !== false ) {
            return $v;
        }

        if ( $legacy_key !== '' ) {
            $v2 = get_user_meta( $user_id, $legacy_key, true );
            if ( $v2 !== '' && $v2 !== null && $v2 !== false ) {
                return $v2;
            }
        }

        return '';
    }

    /**
     * Lee la primera key no-vacía (para compat entre keys viejas y nuevas).
     */
    private function read_any( int $user_id, array $keys ): string {
        foreach ( $keys as $k ) {
            $v = get_user_meta( $user_id, $k, true );
            if ( $v !== '' && $v !== null && $v !== false ) {
                return (string) $v;
            }
        }
        return '';
    }

    public function get_profile( $user_id ) {

        $user_id = (int) $user_id;
        $u = get_userdata( $user_id );

        $profile = array();

        // ====== Básicos ======
        $profile['first_name'] = (string) $this->read_meta_fallback( $user_id, 'bb_tech_first_name', 'bb_profile_first_name' );
        if ( $profile['first_name'] === '' ) {
            $profile['first_name'] = (string) get_user_meta( $user_id, 'first_name', true );
        }

        $profile['last_name'] = (string) $this->read_meta_fallback( $user_id, 'bb_tech_last_name', 'bb_profile_last_name' );
        if ( $profile['last_name'] === '' ) {
            $profile['last_name'] = (string) get_user_meta( $user_id, 'last_name', true );
        }

        $profile['email'] = (string) $this->read_meta_fallback( $user_id, 'bb_tech_email', 'bb_profile_email' );
        if ( $profile['email'] === '' && $u instanceof WP_User ) {
            $profile['email'] = (string) $u->user_email;
        }

        $profile['phone'] = (string) $this->read_meta_fallback( $user_id, 'bb_tech_phone', 'bb_profile_phone' );
        if ( $profile['phone'] === '' ) {
            $profile['phone'] = (string) get_user_meta( $user_id, 'billing_phone', true );
        }

        $profile['bio']    = (string) $this->read_meta_fallback( $user_id, 'bb_tech_bio', 'bb_profile_bio' );
        $profile['radius'] = (string) $this->read_meta_fallback( $user_id, 'bb_tech_area_radius', 'bb_profile_radius' );

        // ====== Languages (array) ======
        $langs = get_user_meta( $user_id, 'bb_tech_languages', true );
        if ( ! is_array( $langs ) ) {
            $langs = get_user_meta( $user_id, 'bb_profile_languages', true );
            if ( ! is_array( $langs ) ) $langs = array();
        }
        $profile['languages'] = $langs;

        $legacy_lang = (string) $this->read_meta_fallback( $user_id, 'bb_tech_language', 'bb_profile_language' );
        if ( empty( $profile['languages'] ) && $legacy_lang !== '' ) {
            $profile['languages'] = array( $legacy_lang );
            $profile['language']  = $legacy_lang;
        }

        // ====== Address (incluye formatted) ======
        $formatted = (string) $this->read_meta_fallback( $user_id, 'bb_tech_address_formatted', 'bb_profile_address_formatted' );
        $line1     = (string) $this->read_meta_fallback( $user_id, 'bb_tech_address_line1',     'bb_profile_address_line1' );

        // city/state/zip: acepta nuevas y viejas
        $city  = $this->read_any( $user_id, array('bb_tech_address_city', 'bb_tech_city', 'bb_profile_city') );
        $state = $this->read_any( $user_id, array('bb_tech_address_state','bb_tech_state','bb_profile_state') );
        $zip   = $this->read_any( $user_id, array('bb_tech_address_zip',  'bb_tech_zip',  'bb_profile_zip') );

        // Woo fallbacks
        if ( $line1 === '' ) $line1 = (string) get_user_meta( $user_id, 'billing_address_1', true );
        if ( $city  === '' ) $city  = (string) get_user_meta( $user_id, 'billing_city', true );
        if ( $state === '' ) $state = (string) get_user_meta( $user_id, 'billing_state', true );
        if ( $zip   === '' ) $zip   = (string) get_user_meta( $user_id, 'billing_postcode', true );

        // Si formatted no existe, lo construimos para mostrar (solo display)
        if ( $formatted === '' ) {
            $tail = trim( implode(' ', array_filter(array($state, $zip))) );
            $parts = array_filter(array($line1, $city, $tail));
            $formatted = $parts ? implode(', ', $parts) : '';
        }

        $profile['address'] = array(
            'formatted' => $formatted,
            'line1'     => $line1,
            'city'      => $city,
            'state'     => $state,
            'zip'       => $zip,
        );

        // ====== Geo ======
        $place_id = $this->read_any( $user_id, array('bb_tech_geo_place_id', 'bb_tech_place_id', 'bb_profile_place_id') );

        $profile['geo'] = array(
            'place_id'    => (string) $place_id,
            'lat'         => $this->read_any( $user_id, array('bb_tech_geo_lat', 'bb_tech_lat', 'bb_profile_lat') ),
            'lng'         => $this->read_any( $user_id, array('bb_tech_geo_lng', 'bb_tech_lng', 'bb_profile_lng') ),
            'status'      => (string) $this->read_meta_fallback( $user_id, 'bb_tech_geocode_status', 'bb_profile_geocode_status' ),
            'error'       => (string) $this->read_meta_fallback( $user_id, 'bb_tech_geocode_error',  'bb_profile_geocode_error' ),
            'geocoded_at' => (string) $this->read_meta_fallback( $user_id, 'bb_tech_geocoded_at',    'bb_profile_geocoded_at' ),
        );

        // ====== Portfolio ======
        $portfolio = get_user_meta( $user_id, 'bb_tech_portfolio', true );
        if ( ! is_array( $portfolio ) ) {
            $portfolio = get_user_meta( $user_id, 'bb_profile_portfolio', true );
            if ( ! is_array( $portfolio ) ) $portfolio = array();
        }
        $profile['portfolio'] = $portfolio;

        // ====== Avatar ======
        $avatar = get_user_meta( $user_id, 'bb_tech_avatar_id', true );
        if ( $avatar === '' || $avatar === null || $avatar === false ) {
            $avatar = get_user_meta( $user_id, 'bb_profile_avatar_id', true );
        }
        $profile['avatar_id'] = (int) $avatar;

        // ====== Status ======
        $status = get_user_meta( $user_id, 'bb_tech_status', true );
        if ( $status === '' || $status === null || $status === false ) {
            $status = get_user_meta( $user_id, 'bb_profile_status', true );
        }
        if ( $status === '' ) $status = 'pending';
        $profile['status'] = (string) $status;

        $profile['user_id'] = $user_id;

        return $profile;
    }

    public function save_profile( $user_id, array $data ) {

        $user_id = (int) $user_id;

        $sf = static function($v){ return sanitize_text_field( (string) ($v ?? '') ); };
        $se = static function($v){ return sanitize_email( (string) ($v ?? '') ); };

        update_user_meta( $user_id, 'bb_tech_first_name', $sf($data['first_name'] ?? '') );
        update_user_meta( $user_id, 'bb_tech_last_name',  $sf($data['last_name']  ?? '') );
        update_user_meta( $user_id, 'bb_tech_email',      $se($data['email']      ?? '') );
        update_user_meta( $user_id, 'bb_tech_phone',      $sf($data['phone']      ?? '') );
        update_user_meta( $user_id, 'bb_tech_bio',        wp_kses_post( (string) ($data['bio'] ?? '') ) );
        update_user_meta( $user_id, 'bb_tech_area_radius',$sf($data['radius']     ?? '') );

        // Idiomas
        $languages = ( isset( $data['languages'] ) && is_array( $data['languages'] ) )
            ? array_values( array_filter( array_map('sanitize_text_field', $data['languages']) ) )
            : array();
        update_user_meta( $user_id, 'bb_tech_languages', $languages );
        if ( ! empty($languages) ) {
            update_user_meta( $user_id, 'bb_tech_language', (string) $languages[0] );
        }

        // Dirección
        $addr = isset( $data['address'] ) && is_array( $data['address'] ) ? $data['address'] : array();

        // ✅ formatted (dirección completa)
        if ( array_key_exists('formatted', $addr) ) {
            update_user_meta( $user_id, 'bb_tech_address_formatted', $sf($addr['formatted']) );
        }

        // Campos por compat / filtros / búsquedas
        if ( array_key_exists('line1', $addr) ) update_user_meta( $user_id, 'bb_tech_address_line1', $sf($addr['line1']) );
        if ( array_key_exists('city',  $addr) ) update_user_meta( $user_id, 'bb_tech_address_city',  $sf($addr['city']) );
        if ( array_key_exists('state', $addr) ) update_user_meta( $user_id, 'bb_tech_address_state', $sf($addr['state']) );
        if ( array_key_exists('zip',   $addr) ) update_user_meta( $user_id, 'bb_tech_address_zip',   $sf($addr['zip']) );

        // Geo (no borra si no viene)
        if ( isset( $data['geo'] ) && is_array( $data['geo'] ) ) {

            if ( array_key_exists( 'place_id', $data['geo'] ) ) {
                $pid = $sf($data['geo']['place_id']);
                update_user_meta( $user_id, 'bb_tech_geo_place_id', $pid );
            }

            if ( array_key_exists( 'lat', $data['geo'] ) ) {
                $lat = $data['geo']['lat'];
                $lat = ( $lat === '' || $lat === null ) ? '' : (float) $lat;
                update_user_meta( $user_id, 'bb_tech_geo_lat', $lat );
            }

            if ( array_key_exists( 'lng', $data['geo'] ) ) {
                $lng = $data['geo']['lng'];
                $lng = ( $lng === '' || $lng === null ) ? '' : (float) $lng;
                update_user_meta( $user_id, 'bb_tech_geo_lng', $lng );
            }

            if ( array_key_exists( 'status', $data['geo'] ) ) {
                update_user_meta( $user_id, 'bb_tech_geocode_status', $sf($data['geo']['status']) );
            }
            if ( array_key_exists( 'error', $data['geo'] ) ) {
                update_user_meta( $user_id, 'bb_tech_geocode_error', $sf($data['geo']['error']) );
            }
            if ( array_key_exists( 'geocoded_at', $data['geo'] ) ) {
                update_user_meta( $user_id, 'bb_tech_geocoded_at', $sf($data['geo']['geocoded_at']) );
            }
        }

        // Portfolio
        $portfolio = array();
        if ( isset($data['portfolio']) && is_array($data['portfolio']) ) {
            foreach ($data['portfolio'] as $u) {
                $u = esc_url_raw( (string) $u );
                if ( $u !== '' ) $portfolio[] = $u;
            }
        }
        update_user_meta( $user_id, 'bb_tech_portfolio', array_values($portfolio) );

        // Avatar
        $avatar_id = isset( $data['avatar_id'] ) ? (int) $data['avatar_id'] : 0;
        update_user_meta( $user_id, 'bb_tech_avatar_id', $avatar_id );

        // Status (solo si viene)
        if ( array_key_exists( 'status', $data ) ) {
            $status = (string) ($data['status'] ?: 'pending');

            if ( ! current_user_can( 'manage_options' ) && $status !== 'pending' ) {
                $status = 'pending';
            }
            update_user_meta( $user_id, 'bb_tech_status', sanitize_text_field($status) );
        }
    }
}
