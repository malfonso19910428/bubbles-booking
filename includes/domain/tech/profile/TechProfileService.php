<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TechProfileService {

    /**
     * @var TechProfileRepo
     */
    private $repo;

    public function __construct( TechProfileRepo $repo ) {
        $this->repo = $repo;

        // Manejar formulario de perfil
        add_action( 'init', array( $this, 'handle_form' ) );
    }

    /**
     * Maneja el formulario "My profile" del técnico
     */
    public function handle_form() {

        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return;
        }

        if (
            ! isset( $_POST['bb_profile_action'] ) ||
            $_POST['bb_profile_action'] !== 'save'
        ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        if (
            ! isset( $_POST['bb_profile_nonce'] ) ||
            ! wp_verify_nonce( $_POST['bb_profile_nonce'], 'bb_save_profile' )
        ) {
            wp_die( 'Security check failed', 403 );
        }

        $user_id = get_current_user_id();

        // Datos crudos del formulario
        $raw = isset( $_POST['profile'] ) && is_array( $_POST['profile'] )
            ? $_POST['profile']
            : array();

        // Datos normalizados desde POST (sin avatar aún)
        $data = $this->normalize( $raw );

        // --- FOTO DE PERFIL (avatar) DESDE INPUT FILE ---
        if (
            isset( $_FILES['bb_profile_avatar'] ) &&
            ! empty( $_FILES['bb_profile_avatar']['name'] )
        ) {
            $file = $_FILES['bb_profile_avatar'];

            // Funciones de subida de archivos
            require_once ABSPATH . 'wp-admin/includes/file.php';

            $uploaded = wp_handle_upload( $file, array( 'test_form' => false ) );

            if ( empty( $uploaded['error'] ) ) {

                // Crear attachment
                $attachment = array(
                    'post_mime_type' => $uploaded['type'],
                    'post_title'     => sanitize_file_name( $file['name'] ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                    'post_author'    => $user_id,
                );

                $attachment_id = wp_insert_attachment( $attachment, $uploaded['file'] );

                if ( $attachment_id ) {
                    // Metadata de imagen
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    $attach_data = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
                    wp_update_attachment_metadata( $attachment_id, $attach_data );

                    // Guardamos el nuevo avatar_id en los datos del perfil
                    $data['avatar_id'] = (int) $attachment_id;
                }
            }
        }

        // Perfil actual para comparar cambios "sensibles"
        $current = $this->repo->get_profile( $user_id );

        $needs_review = false;

        // --- Avatar ---
        $new_avatar = isset( $data['avatar_id'] ) ? (int) $data['avatar_id'] : 0;
        $old_avatar = isset( $current['avatar_id'] ) ? (int) $current['avatar_id'] : 0;
        if ( $new_avatar !== $old_avatar ) {
            $needs_review = true;
        }

        // --- Bio ---
        $new_bio = isset( $data['bio'] ) ? (string) $data['bio'] : '';
        $old_bio = isset( $current['bio'] ) ? (string) $current['bio'] : '';
        if ( $new_bio !== $old_bio ) {
            $needs_review = true;
        }

        // --- Dirección ---
        $new_addr = isset( $data['address'] ) && is_array( $data['address'] )
            ? $data['address']
            : array();

        $old_addr = isset( $current['address'] ) && is_array( $current['address'] )
            ? $current['address']
            : array();

        foreach ( array( 'line1', 'city', 'state', 'zip' ) as $field ) {
            $new_val = isset( $new_addr[ $field ] ) ? (string) $new_addr[ $field ] : '';
            $old_val = isset( $old_addr[ $field ] ) ? (string) $old_addr[ $field ] : '';
            if ( $new_val !== $old_val ) {
                $needs_review = true;
                break;
            }
        }

        // --- Teléfono ---
        $new_phone = isset( $data['phone'] ) ? (string) $data['phone'] : '';
        $old_phone = isset( $current['phone'] ) ? (string) $current['phone'] : '';
        if ( $new_phone !== $old_phone ) {
            $needs_review = true;
        }

        // Si hubo cambios sensibles → marcar como "pending"
        if ( $needs_review ) {
            $data['status'] = 'pending';
        }

        // Guardar perfil en usermeta vía Repo
        $this->repo->save_profile( $user_id, $data );

        // Redirección de vuelta con flag de guardado
        $redirect = wp_get_referer();
        if ( ! $redirect ) {
            $redirect = add_query_arg( 'bb_view', 'profile', get_permalink() );
        }

        $redirect = add_query_arg( 'profile_saved', '1', $redirect );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Normaliza los datos crudos del formulario
     *
     * @param array $raw
     * @return array
     */
    private function normalize( array $raw ) {

        $data = array();

        // Básico
        $data['first_name'] = isset( $raw['first_name'] )
            ? sanitize_text_field( $raw['first_name'] )
            : '';

        $data['last_name'] = isset( $raw['last_name'] )
            ? sanitize_text_field( $raw['last_name'] )
            : '';

        $data['email'] = isset( $raw['email'] )
            ? sanitize_email( $raw['email'] )
            : '';

        $data['phone'] = isset( $raw['phone'] )
            ? sanitize_text_field( $raw['phone'] )
            : '';

        $data['bio'] = isset( $raw['bio'] )
            ? wp_kses_post( $raw['bio'] )
            : '';

        $data['radius'] = isset( $raw['radius'] )
            ? floatval( $raw['radius'] )
            : 0;

        // Idiomas (array de códigos: en, es, pt, etc.)
        $langs_raw = ( isset( $raw['languages'] ) && is_array( $raw['languages'] ) )
            ? $raw['languages']
            : array();

        $languages = array();
        foreach ( $langs_raw as $code ) {
            $code = sanitize_text_field( $code );
            if ( $code !== '' ) {
                $languages[] = $code;
            }
        }
        $data['languages'] = $languages;

        // Dirección
        $addr = array();
        $addr['line1'] = isset( $raw['address_line1'] ) ? sanitize_text_field( $raw['address_line1'] ) : '';
        $addr['city']  = isset( $raw['city'] )          ? sanitize_text_field( $raw['city'] )          : '';
        $addr['state'] = isset( $raw['state'] )         ? sanitize_text_field( $raw['state'] )         : '';
        $addr['zip']   = isset( $raw['zip'] )           ? sanitize_text_field( $raw['zip'] )           : '';

        $data['address'] = $addr;

        // Portfolio: URLs una por línea (si sigues usando esto)
        $portfolio_urls_raw = isset( $raw['portfolio_urls'] ) ? $raw['portfolio_urls'] : '';
        $portfolio_lines    = preg_split( '/\r\n|\r|\n/', $portfolio_urls_raw );
        $portfolio          = array();

        if ( is_array( $portfolio_lines ) ) {
            foreach ( $portfolio_lines as $url ) {
                $url = trim( $url );
                if ( $url === '' ) {
                    continue;
                }
                $clean = esc_url_raw( $url );
                if ( $clean ) {
                    $portfolio[] = $clean;
                }
            }
        }

        $data['portfolio'] = $portfolio;

        // Avatar desde hidden (ej. admin) – el upload por file lo puede sobreescribir
        if ( isset( $raw['avatar_id'] ) ) {
            $data['avatar_id'] = (int) $raw['avatar_id'];
        }

        return $data;
    }

    /**
     * Devuelve los datos de perfil listos para la vista
     *
     * @param int|null $user_id
     * @return array
     */
    public function profile_for_user( $user_id = null ) {

        if ( ! $user_id ) {
            if ( ! is_user_logged_in() ) {
                return array();
            }
            $user_id = get_current_user_id();
        }

        return $this->repo->get_profile( $user_id );
    }
}
