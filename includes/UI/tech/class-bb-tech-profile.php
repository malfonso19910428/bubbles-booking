<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tech Profile Controller
 * - POST: valida/guarda vía Service, guarda flash, redirect.
 * - GET: entrega view data para template limpia.
 */
final class BB_Tech_Profile_Controller {

    private TechProfileService $service;

    public function __construct( TechProfileService $service ) {
        $this->service = $service;
    }

    public function register_hooks(): void {
        add_action( 'wp_loaded', array( $this, 'handle_request' ), 1 );
    }

    public function handle_request(): void {

        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) return;
        if ( ! is_user_logged_in() ) return;

        $action = $_POST['bb_profile_action'] ?? '';
        if ( $action !== 'save' ) return;

        // ✅ si no viene payload, no es nuestro form
        if ( empty($_POST['profile']) || ! is_array($_POST['profile']) ) return;

        // Nonce
        if (
            empty( $_POST['bb_profile_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['bb_profile_nonce'] ) ),
                'bb_save_profile'
            )
        ) {
            wp_die( 'Security check failed', 403 );
        }

        $user_id = (int) get_current_user_id();

        // payload base
        $payload = wp_unslash( $_POST['profile'] );

        // =========================
        // ✅ AVATAR UPLOAD (DEVICE)
        // =========================
        $upload_error = '';

        if ( ! empty($_FILES['bb_profile_avatar']) && ! empty($_FILES['bb_profile_avatar']['name']) ) {

            // seguridad mínima: solo imágenes
            $type = (string)($_FILES['bb_profile_avatar']['type'] ?? '');
            if ( strpos($type, 'image/') !== 0 ) {
                $upload_error = 'Please upload a valid image file.';
            } else {

                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                // parent post = 0 (attachment suelto)
                $attachment_id = media_handle_upload( 'bb_profile_avatar', 0 );

                if ( is_wp_error($attachment_id) ) {
                    $upload_error = $attachment_id->get_error_message();
                } else {
                    // ✅ inyecta en payload para que el service lo guarde
                    $payload['avatar_id'] = (int) $attachment_id;
                }
            }
        }

        // ✅ Service hace: normalize + sanitize + validate + save
        $result = $this->service->save_from_form( $user_id, $payload );

        // si falló el upload, lo metemos en errors aunque el service haya guardado lo demás
        if ( $upload_error !== '' ) {
            if ( empty($result['errors']) || ! is_array($result['errors']) ) {
                $result['errors'] = array();
            }
            $result['errors']['avatar'] = $upload_error;
        }

        // ✅ Flash
        $this->flash_set( $user_id, array(
            'saved'    => ! empty( $result['saved'] ),
            'errors'   => ( isset($result['errors'])   && is_array($result['errors']) )   ? $result['errors']   : array(),
            'messages' => ( isset($result['messages']) && is_array($result['messages']) ) ? $result['messages'] : array(),
        ) );

        // ✅ Redirect estable
        $fallback = home_url( '/staff-dashboard/?bb_view=profile' );
        $redirect = $fallback;

        $ref = wp_get_referer();
        if ( $ref ) {
            $redirect = remove_query_arg(
                array('bb_profile_nonce','_wpnonce','_wp_http_referer'),
                $ref
            );
        }

        if ( strpos( $redirect, '/wp-admin/' ) !== false ) {
            $redirect = $fallback;
        }

        wp_safe_redirect( esc_url_raw( $redirect ) );
        exit;
    }

    public function get_view_data( int $user_id ): array {

        $profile = method_exists( $this->service, 'get_profile_for_view' )
            ? $this->service->get_profile_for_view( $user_id )
            : $this->service->get_profile( $user_id );

        $flash = $this->flash_get( $user_id );

        $ui = array(
            'messages'      => ( isset($flash['messages']) && is_array($flash['messages']) ) ? $flash['messages'] : array(),
            'errors'        => ( isset($flash['errors'])   && is_array($flash['errors']) )   ? $flash['errors']   : array(),
            'profile_saved' => ! empty( $flash['saved'] ),

            'is_incomplete' => $this->is_incomplete($profile),
            'preview_text'  => $this->address_preview_text($profile),
        );

        return array(
            'profile' => $profile,
            'ui'      => $ui,
        );
    }

    // =========================
    // Flash helpers (Transient)
    // =========================
    private function flash_key( int $user_id ): string {
        return 'bb_profile_flash_' . (int) $user_id;
    }

    private function flash_set( int $user_id, array $flash ): void {
        set_transient( $this->flash_key($user_id), $flash, 30 );
    }

    private function flash_get( int $user_id ): array {
        $key = $this->flash_key($user_id);
        $val = get_transient( $key );
        delete_transient( $key );
        return is_array($val) ? $val : array();
    }

    // =========================
    // UI helpers
    // =========================
    private function is_incomplete( array $profile ): bool {
        $fn = (string)($profile['first_name'] ?? '');
        $ln = (string)($profile['last_name'] ?? '');
        $em = (string)($profile['email'] ?? '');
        return ($fn === '' || $ln === '' || $em === '');
    }

    private function address_preview_text( array $profile ): string {
        $a = ( isset($profile['address']) && is_array($profile['address']) ) ? $profile['address'] : array();
        $parts = array_filter(array(
            (string)($a['city'] ?? ''),
            (string)($a['state'] ?? ''),
            (string)($a['zip'] ?? ''),
        ));
        return $parts ? implode(', ', $parts) : '-';
    }
}
