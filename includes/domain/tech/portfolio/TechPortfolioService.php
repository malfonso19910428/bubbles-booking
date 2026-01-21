<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class TechProfileService {

    /** @var TechProfileRepo */
    private $repo;

    public function __construct( TechProfileRepo $repo ) {
        $this->repo = $repo;
    }

    // =========================
    // GET
    // =========================

    public function get_profile( int $user_id ): array {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) return array();

        $user = get_user_by( 'id', $user_id );

        // Defaults desde WP (por si meta está vacío)
        $wp_first = $user ? (string) $user->first_name : '';
        $wp_last  = $user ? (string) $user->last_name : '';
        $wp_email = $user ? (string) $user->user_email : '';

        return array(
            'user_id'    => $user_id,
            'status'     => (string) $this->repo->get_meta( $user_id, 'bb_tech_status', 'pending' ),

            'avatar_id'  => (int) $this->repo->get_meta( $user_id, 'bb_tech_avatar_id', 0 ),

            'first_name' => (string) $this->repo->get_meta( $user_id, 'bb_tech_first_name', $wp_first ),
            'last_name'  => (string) $this->repo->get_meta( $user_id, 'bb_tech_last_name',  $wp_last  ),
            'email'      => (string) $this->repo->get_meta( $user_id, 'bb_tech_email',      $wp_email ),
            'phone'      => (string) $this->repo->get_meta( $user_id, 'bb_tech_phone', '' ),
            'bio'        => (string) $this->repo->get_meta( $user_id, 'bb_tech_bio', '' ),
            'radius'     => (int)    $this->repo->get_meta( $user_id, 'bb_tech_radius', 0 ),

            'address'    => array(
                'line1'     => (string) $this->repo->get_meta( $user_id, 'bb_tech_address_line1', '' ),
                'city'      => (string) $this->repo->get_meta( $user_id, 'bb_tech_address_city', '' ),
                'state'     => (string) $this->repo->get_meta( $user_id, 'bb_tech_address_state', '' ),
                'zip'       => (string) $this->repo->get_meta( $user_id, 'bb_tech_address_zip', '' ),
                'formatted' => (string) $this->repo->get_meta( $user_id, 'bb_tech_address_formatted', '' ),
            ),

            'geo'        => array(
                'place_id' => (string) $this->repo->get_meta( $user_id, 'bb_tech_geo_place_id', '' ),
                'lat'      => (string) $this->repo->get_meta( $user_id, 'bb_tech_geo_lat', '' ),
                'lng'      => (string) $this->repo->get_meta( $user_id, 'bb_tech_geo_lng', '' ),
            ),

            'languages'  => $this->repo->get_meta_array( $user_id, 'bb_tech_languages', array() ),
        );
    }

    public function get_profile_for_view( int $user_id ): array {
        return $this->get_profile( $user_id );
    }

    // =========================
    // SAVE
    // =========================

    /**
     * Guarda desde el form del perfil técnico.
     * $payload viene de $_POST['profile'] (ya wp_unslash)
     */
    public function save_from_form( int $user_id, array $payload ): array {

        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return array(
                'saved'    => false,
                'errors'   => array( 'general' => 'Invalid user.' ),
                'messages' => array(),
            );
        }

        // ---- normalize arrays
        $addr  = ( isset($payload['address']) && is_array($payload['address']) ) ? $payload['address'] : array();
        $geo   = ( isset($payload['geo']) && is_array($payload['geo']) ) ? $payload['geo'] : array();
        $langs = ( isset($payload['languages']) && is_array($payload['languages']) ) ? $payload['languages'] : array();

        // ---- sanitize basics
        $first = sanitize_text_field( (string) ($payload['first_name'] ?? '') );
        $last  = sanitize_text_field( (string) ($payload['last_name'] ?? '') );
        $email = sanitize_email( (string) ($payload['email'] ?? '') );
        $phone = sanitize_text_field( (string) ($payload['phone'] ?? '') );
        $bio   = wp_kses_post( (string) ($payload['bio'] ?? '') );

        $radius = isset($payload['radius']) ? (int) $payload['radius'] : 0;
        if ( $radius < 0 ) $radius = 0;

        // ---- Address (extras opcionales)
        $line1     = trim( sanitize_text_field( (string) ($addr['line1'] ?? '') ) );
        $city      = trim( sanitize_text_field( (string) ($addr['city'] ?? '') ) );
        $state     = trim( sanitize_text_field( (string) ($addr['state'] ?? '') ) );
        $zip       = trim( sanitize_text_field( (string) ($addr['zip'] ?? '') ) );

        // ✅ Campo principal único (lo que se ve / lo que se guarda)
        $formatted = trim( sanitize_text_field( (string) ($addr['formatted'] ?? '') ) );

        // ---- Geo (opcional)
        $place_id = sanitize_text_field( (string) ($geo['place_id'] ?? '') );
        $lat = isset($geo['lat']) ? (string) $geo['lat'] : '';
        $lng = isset($geo['lng']) ? (string) $geo['lng'] : '';
        $lat = preg_replace('/[^0-9\.\-]/', '', $lat);
        $lng = preg_replace('/[^0-9\.\-]/', '', $lng);

        // ---- Avatar
        $avatar_id = isset($payload['avatar_id']) ? (int) $payload['avatar_id'] : 0;

        // ---- Languages allowlist
        $allow_langs = array( 'en', 'es', 'pt' );
        $clean_langs = array();
        foreach ( $langs as $l ) {
            $l = sanitize_text_field( (string) $l );
            if ( in_array( $l, $allow_langs, true ) ) $clean_langs[] = $l;
        }
        $clean_langs = array_values( array_unique( $clean_langs ) );

        // =========================
        // VALIDATION
        // =========================
        $errors = array();

        if ( $first === '' ) $errors['first_name'] = 'First name is required.';
        if ( $last === '' )  $errors['last_name']  = 'Last name is required.';
        if ( $email === '' || ! is_email($email) ) $errors['email'] = 'Valid email is required.';

        // ✅ Address: si tocaron address, formatted debe existir
        // (esto funciona perfecto con el HARD SYNC on submit del JS)
        $address_touched = ( $formatted !== '' || $line1 !== '' || $city !== '' || $state !== '' || $zip !== '' || $place_id !== '' );

        if ( $address_touched && $formatted === '' ) {
            $errors['address'] = 'Please enter your full address.';
        }

        // ✅ Si quieres forzar SÍ O SÍ que seleccionen sugerencia, descomenta:
        /*
        if ( $address_touched && $place_id === '' ) {
            $errors['address'] = 'Please select a valid address from suggestions.';
        }
        */

        if ( ! empty($errors) ) {
            return array(
                'saved'    => false,
                'errors'   => $errors,
                'messages' => array(
                    array('type' => 'error', 'text' => 'Please fix the errors and try again.')
                ),
            );
        }

        // =========================
        // SAVE META
        // =========================
        $this->repo->update_meta( $user_id, 'bb_tech_first_name', $first );
        $this->repo->update_meta( $user_id, 'bb_tech_last_name',  $last );
        $this->repo->update_meta( $user_id, 'bb_tech_email',      $email );
        $this->repo->update_meta( $user_id, 'bb_tech_phone',      $phone );
        $this->repo->update_meta( $user_id, 'bb_tech_bio',        $bio );
        $this->repo->update_meta( $user_id, 'bb_tech_radius',     $radius );

        // ✅ extras opcionales (si quieres usarlos después)
        $this->repo->update_meta( $user_id, 'bb_tech_address_line1', $line1 );
        $this->repo->update_meta( $user_id, 'bb_tech_address_city',  $city );
        $this->repo->update_meta( $user_id, 'bb_tech_address_state', $state );
        $this->repo->update_meta( $user_id, 'bb_tech_address_zip',   $zip );

        // ✅ CAMPO PRINCIPAL (verdad única)
        $this->repo->update_meta( $user_id, 'bb_tech_address_formatted', $formatted );

        // geo opcional
        $this->repo->update_meta( $user_id, 'bb_tech_geo_place_id', $place_id );
        $this->repo->update_meta( $user_id, 'bb_tech_geo_lat',      $lat );
        $this->repo->update_meta( $user_id, 'bb_tech_geo_lng',      $lng );

        $this->repo->update_meta( $user_id, 'bb_tech_avatar_id', $avatar_id );

        $this->repo->update_meta_array( $user_id, 'bb_tech_languages', $clean_langs );

        return array(
            'saved'    => true,
            'errors'   => array(),
            'messages' => array(
                array('type' => 'success', 'text' => 'Profile saved.')
            ),
        );
    }
}
