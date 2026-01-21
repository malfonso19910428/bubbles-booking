<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TechPortfolioService {

    /**
     * @var TechPortfolioRepo
     */
    protected $repo;

    public function __construct( TechPortfolioRepo $repo ) {
        $this->repo = $repo;

        // 🔹 El técnico envía sus trabajos desde un formulario
        add_action( 'init', array( $this, 'handle_form' ) );
    }

    /**
     * Maneja el formulario de envío de trabajos del técnico
     *
     * Form esperado:
     *  - bb_portfolio_action = 'submit'
     *  - bb_portfolio_nonce  (nonce)
     *  - image_id            (attachment ID)
     *  - title               (opcional)
     *  - description         (opcional)
     */
    public function handle_form() {

        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return;
        }

        if (
            ! isset( $_POST['bb_portfolio_action'] ) ||
            $_POST['bb_portfolio_action'] !== 'submit'
        ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        if (
            ! isset( $_POST['bb_portfolio_nonce'] ) ||
            ! wp_verify_nonce( $_POST['bb_portfolio_nonce'], 'bb_save_portfolio' )
        ) {
            wp_die( 'Security check failed', 403 );
        }

        $user_id = get_current_user_id();

        // Validar rol básico (bb_staff o admin)
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;

        if ( ! array_intersect( array( 'bb_staff', 'administrator' ), $roles ) ) {
            wp_die( 'You do not have permission to submit portfolio items.', 403 );
        }

        // Leer datos del formulario
        $image_id    = isset( $_POST['image_id'] ) ? (int) $_POST['image_id'] : 0;
        $title       = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( $_POST['description'] ) : '';

        if ( $image_id <= 0 ) {
            // Si no hay imagen, no hacemos nada
            return;
        }

        // 🔹 Crea el item en estado "pending"
        $this->submit_item( $user_id, $image_id, $title, $description );

        // Redirigir de vuelta a la vista "portfolio"
        $redirect = wp_get_referer();
        if ( ! $redirect ) {
            $redirect = add_query_arg( 'bb_view', 'portfolio', get_permalink() );
        }

        $redirect = add_query_arg( 'portfolio_saved', '1', $redirect );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Envía un nuevo item de portfolio (queda en pending)
     */
    public function submit_item( $tech_id, $image_id, $title = '', $description = '' ) {

        $tech_id     = (int) $tech_id;
        $image_id    = (int) $image_id;
        $title       = sanitize_text_field( $title );
        $description = wp_kses_post( $description );

        if ( $tech_id <= 0 || $image_id <= 0 ) {
            return 0;
        }

        return $this->repo->create_pending( $tech_id, $image_id, $title, $description );
    }

    /**
     * Portfolio público (solo aprobados)
     *
     * Esto es lo que mostrarás al cliente en el frontend.
     */
    public function get_public_portfolio( $tech_id ) {
        $tech_id = (int) $tech_id;
        if ( $tech_id <= 0 ) {
            return array();
        }

        return $this->repo->get_approved_for_tech( $tech_id );
    }

    /**
     * Items pendientes para ese técnico (para que vea lo que está "en revisión")
     */
    public function get_pending_for_tech( $tech_id ) {
        $tech_id = (int) $tech_id;
        if ( $tech_id <= 0 ) {
            return array();
        }

        return $this->repo->get_pending_for_tech( $tech_id );
    }

    // 👇 Estos métodos son para cuando más adelante quieras admin:
    // lista para admin, aprobar, rechazar, etc.

    public function get_pending_items_for_admin( $limit = 50 ) {
        return $this->repo->get_pending_items( $limit );
    }

    public function approve_item( $item_id, $admin_id ) {
        $item_id  = (int) $item_id;
        $admin_id = (int) $admin_id;

        if ( $item_id <= 0 || $admin_id <= 0 ) {
            return false;
        }

        return $this->repo->update_status( $item_id, 'approved', $admin_id );
    }

    public function reject_item( $item_id, $admin_id ) {
        $item_id  = (int) $item_id;
        $admin_id = (int) $admin_id;

        if ( $item_id <= 0 || $admin_id <= 0 ) {
            return false;
        }

        return $this->repo->update_status( $item_id, 'rejected', $admin_id );
    }
}
.