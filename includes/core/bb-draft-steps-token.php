<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class BB_Draft_Steps_Token {

    const COOKIE_NAME = 'bb_draft_token';

    /**
     * Devuelve token persistente.
     *
     * Prioridad segura:
     * 1) COOKIE (si existe)
     * 2) POST bb_token (si no hay cookie)
     * 3) GET  bb_token (si no hay cookie)
     * 4) Crear token nuevo + set cookie
     *
     * Nota: Si existe cookie, NO aceptamos otro token distinto por GET/POST.
     * Esto evita crear drafts nuevos por fallos de form/step o links con token.
     */
    public static function get_or_create(): string {

        // 1) COOKIE primero (fuente de verdad)
        $cookie_token = '';
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $cookie_token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
            $cookie_token = trim( $cookie_token );
            if ( $cookie_token !== '' ) {
                return $cookie_token;
            }
        }

        // 2) POST si no hay cookie
        if ( isset( $_POST['bb_token'] ) ) {
            $t = sanitize_text_field( wp_unslash( $_POST['bb_token'] ) );
            $t = trim( $t );
            if ( self::is_valid_token( $t ) ) {
                self::set_cookie( $t );
                return $t;
            }
        }

        // 3) GET si no hay cookie
        if ( isset( $_GET['bb_token'] ) ) {
            $t = sanitize_text_field( wp_unslash( $_GET['bb_token'] ) );
            $t = trim( $t );
            if ( self::is_valid_token( $t ) ) {
                self::set_cookie( $t );
                return $t;
            }
        }

        // 4) Crear token nuevo
        $token = wp_generate_password( 32, false, false );
        self::set_cookie( $token );
        return $token;
    }

    /**
     * ✅ Valida formato básico del token (solo para evitar basura/short)
     */
    private static function is_valid_token( string $t ): bool {
        if ( $t === '' ) return false;
        if ( strlen( $t ) < 16 || strlen( $t ) > 128 ) return false;
        // wp_generate_password(..., false, false) devuelve alfanumérico, pero aceptamos -_ por si migras a uuid
        return (bool) preg_match( '/^[a-zA-Z0-9\-_]+$/', $t );
    }

    /**
     * Set cookie de token (y lo hace visible en el request actual).
     */
    private static function set_cookie( string $token ): void {

        if ( headers_sent() ) {
            // al menos disponible en este request
            $_COOKIE[ self::COOKIE_NAME ] = $token;
            return;
        }

        $secure   = is_ssl();
        $httponly = true;

        setcookie(
            self::COOKIE_NAME,
            $token,
            time() + ( 7 * DAY_IN_SECONDS ),
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
            $secure,
            $httponly
        );

        $_COOKIE[ self::COOKIE_NAME ] = $token;
    }
}
