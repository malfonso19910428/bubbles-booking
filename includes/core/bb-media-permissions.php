<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Permisos de media para técnicos (bb_staff)
 *
 * Objetivo:
 * - Que los usuarios con rol bb_staff solo puedan ver SUS PROPIOS archivos
 *   en la librería de medios (popup y pantalla de Medios).
 * - Los administradores siguen viendo todo normalmente.
 */

/**
 * (Opcional) Log rápido para verificar que el archivo se está cargando.
 * Puedes comentar esta función cuando ya verifiques que funciona.
 */
function bb_media_permissions_loaded_log() {
    // Descomenta la siguiente línea si quieres ver el log en debug.log
    // error_log( 'BB Media Permissions: archivo cargado ✅' );
}
add_action( 'init', 'bb_media_permissions_loaded_log' );

/**
 * Restringir la librería de medios en el popup (media modal)
 * para que los técnicos (bb_staff) solo vean sus propios uploads.
 *
 * Este filtro se ejecuta cuando el popup de medios hace la llamada AJAX
 * para cargar los adjuntos.
 */
function bb_limit_media_popup_to_owner( $query ) {

    // Usuario actual
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return $query;
    }

    $user  = wp_get_current_user();
    $roles = (array) $user->roles;

    // Si el usuario es admin (o tiene manage_options), no limitar
    if ( current_user_can( 'manage_options' ) ) {
        return $query;
    }

    // Solo queremos limitar a los técnicos bb_staff
    if ( in_array( 'bb_staff', $roles, true ) ) {

        // Forzamos el autor al usuario actual
        $query['author'] = $user_id;

        // (Opcional) Log de depuración:
        // error_log( 'BB Media Popup: limitando adjuntos al autor ' . $user_id );
        // error_log( 'BB Media Popup query: ' . print_r( $query, true ) );
    }

    return $query;
}
add_filter( 'ajax_query_attachments_args', 'bb_limit_media_popup_to_owner' );



/**
 * Restringir la lista de Medios en admin para bb_staff
 *
 * Este hook afecta:
 * - La pantalla de "Medios → Biblioteca" en el admin.
 * - Cualquier listado de adjuntos que use WP_Query en el admin.
 */
function bb_limit_media_list_to_owner( $wp_query ) {

    // Solo en admin (incluye admin-ajax)
    if ( ! is_admin() ) {
        return;
    }

    // Solo tocar consultas de adjuntos
    if ( $wp_query->get( 'post_type' ) !== 'attachment' ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    $user  = wp_get_current_user();
    $roles = (array) $user->roles;

    // No tocar admins
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    // Solo limitar a técnicos
    if ( in_array( 'bb_staff', $roles, true ) ) {

        // Forzamos el filtro por autor
        $wp_query->set( 'author', $user_id );

        // (Opcional) Log de depuración:
        // error_log( 'BB pre_get_posts: limitando adjuntos al autor ' . $user_id );
    }
}
add_action( 'pre_get_posts', 'bb_limit_media_list_to_owner' );
