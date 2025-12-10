<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TechProfileRepo
 *
 * Capa de acceso a datos para el perfil del técnico.
 * Usa exclusivamente usermeta (wp_usermeta).
 */
class TechProfileRepo {

    /**
     * Devuelve el perfil completo del técnico como array.
     *
     * @param int $user_id
     * @return array
     */
    public function get_profile( $user_id ) {

        $user_id = (int) $user_id;
        $profile = array();

        // Datos básicos
        $profile['first_name'] = get_user_meta( $user_id, 'bb_profile_first_name', true );
        $profile['last_name']  = get_user_meta( $user_id, 'bb_profile_last_name', true );
        $profile['email']      = get_user_meta( $user_id, 'bb_profile_email', true );
        $profile['phone']      = get_user_meta( $user_id, 'bb_profile_phone', true );
        $profile['bio']        = get_user_meta( $user_id, 'bb_profile_bio', true );
        $profile['radius']     = get_user_meta( $user_id, 'bb_profile_radius', true );

        // Idiomas como array
        $langs = get_user_meta( $user_id, 'bb_profile_languages', true );
        if ( ! is_array( $langs ) ) {
            $langs = array();
        }
        $profile['languages'] = $langs;

        // Compatibilidad vieja: un solo language
        $legacy_lang = get_user_meta( $user_id, 'bb_profile_language', true );
        if ( empty( $profile['languages'] ) && ! empty( $legacy_lang ) ) {
            $profile['languages'] = array( $legacy_lang );
            $profile['language']  = $legacy_lang;
        }

        // Dirección
        $profile['address'] = array(
            'line1' => get_user_meta( $user_id, 'bb_profile_address_line1', true ),
            'city'  => get_user_meta( $user_id, 'bb_profile_city',          true ),
            'state' => get_user_meta( $user_id, 'bb_profile_state',         true ),
            'zip'   => get_user_meta( $user_id, 'bb_profile_zip',           true ),
        );

        // Portfolio: array de URLs
        $portfolio = get_user_meta( $user_id, 'bb_profile_portfolio', true );
        if ( ! is_array( $portfolio ) ) {
            $portfolio = array();
        }
        $profile['portfolio'] = $portfolio;

        // Avatar (attachment ID)
        $profile['avatar_id'] = (int) get_user_meta( $user_id, 'bb_profile_avatar_id', true );

        // Status del perfil (pending / approved / rejected, etc.)
        $status = get_user_meta( $user_id, 'bb_profile_status', true );
        if ( $status === '' ) {
            $status = 'pending'; // valor por defecto
        }
        $profile['status'] = $status;

        return $profile;
    }

    /**
     * Guarda el perfil del técnico.
     *
     * @param int   $user_id
     * @param array $data   (first_name, last_name, email, phone, bio, radius, languages[], address[], portfolio[], avatar_id, status?)
     */
    public function save_profile( $user_id, array $data ) {

        $user_id = (int) $user_id;

        update_user_meta( $user_id, 'bb_profile_first_name', $data['first_name'] ?? '' );
        update_user_meta( $user_id, 'bb_profile_last_name',  $data['last_name']  ?? '' );
        update_user_meta( $user_id, 'bb_profile_email',      $data['email']      ?? '' );
        update_user_meta( $user_id, 'bb_profile_phone',      $data['phone']      ?? '' );
        update_user_meta( $user_id, 'bb_profile_bio',        $data['bio']        ?? '' );
        update_user_meta( $user_id, 'bb_profile_radius',     $data['radius']     ?? '' );

        // Idiomas (array)
        $languages = ( isset( $data['languages'] ) && is_array( $data['languages'] ) )
            ? array_values( $data['languages'] )   // reindexar por si acaso
            : array();

        update_user_meta( $user_id, 'bb_profile_languages', $languages );

        // Dirección
        $addr = isset( $data['address'] ) && is_array( $data['address'] )
            ? $data['address']
            : array();

        update_user_meta( $user_id, 'bb_profile_address_line1', $addr['line1'] ?? '' );
        update_user_meta( $user_id, 'bb_profile_city',          $addr['city']  ?? '' );
        update_user_meta( $user_id, 'bb_profile_state',         $addr['state'] ?? '' );
        update_user_meta( $user_id, 'bb_profile_zip',           $addr['zip']   ?? '' );

        // Portfolio (array de URLs)
        $portfolio = ( isset( $data['portfolio'] ) && is_array( $data['portfolio'] ) )
            ? array_values( $data['portfolio'] )
            : array();

        update_user_meta( $user_id, 'bb_profile_portfolio', $portfolio );

        // Avatar (attachment ID)
        $avatar_id = isset( $data['avatar_id'] ) ? (int) $data['avatar_id'] : 0;
        update_user_meta( $user_id, 'bb_profile_avatar_id', $avatar_id );

        // Status del perfil
        // Solo lo tocamos si viene explícitamente en $data['status'].
        if ( array_key_exists( 'status', $data ) ) {
            $status = $data['status'] ?: 'pending';

            // Si NO es admin, por seguridad no dejamos que fuerce otro estado distinto de pending
            if ( ! current_user_can( 'manage_options' ) && $status !== 'pending' ) {
                $status = 'pending';
            }

            update_user_meta( $user_id, 'bb_profile_status', $status );
        }
    }
}
