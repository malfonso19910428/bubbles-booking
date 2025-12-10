<?php
if ( ! defined( 'ABSPATH' ) ) exit;


   class Bubbles_Roles {

    /**
     * Registrar o actualizar roles personalizados del plugin
     */
    public static function register_roles() {

        // Capabilities deseadas para bb_staff
        $caps = array(
            'read'         => true,
            'upload_files' => true,   // ✅ permiso para subir imágenes
            'edit_posts'   => false,
            'delete_posts' => false,
            'publish_posts'=> false,
        );

        // Si el rol NO existe, lo creamos
        if ( ! get_role( 'bb_staff' ) ) {

            add_role(
                'bb_staff',
                'Staff',
                $caps
            );

        } else {
            // Si ya existe, lo actualizamos
            $role = get_role( 'bb_staff' );

            if ( $role ) {
                foreach ( $caps as $cap => $grant ) {
                    if ( $grant ) {
                        $role->add_cap( $cap );
                    } else {
                        $role->remove_cap( $cap );
                    }
                }
            }
        }
    }

    /**
     * (Opcional) Eliminar roles al desactivar el plugin
     */
    public static function remove_roles() {
        remove_role( 'bb_staff' );
    }
}


