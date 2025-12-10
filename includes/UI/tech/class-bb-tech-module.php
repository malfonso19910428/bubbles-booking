<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo técnico:
 * - Registra el shortcode [bb_staff_dashboard]
 * - Verifica login y rol (bb_staff o administrator)
 * - Instancia dominio (Repo + Service) para availability
 * - Instancia dominio (Repo + Service) para profile
 * - Instancia dominio (Repo + Service) para portfolio (fotos técnico)
 * - Instancia la shell/layout del dashboard
 * - Encola CSS/JS del módulo técnico
 */
class BB_Tech_Module {

    /** @var BB_Tech_Shell|null */
    protected $shell = null;

    /** @var TechAvailabilityService|null */
    protected $availability_service = null;

    /** @var TechProfileService|null */
    protected $profile_service = null;

    /** @var TechPortfolioService|null */
    protected $portfolio_service = null;

    public function __construct() {
        error_log( 'Bubbles Booking: ✅ BB_Tech_Module constructor ejecutado.' );

        // 1) Incluir clases necesarias
        $this->includes();

        // 2) Instanciar clases internas (dominio + shell)
        $this->init_classes();

        // 3) Shortcode del dashboard técnico
        add_shortcode( 'bb_staff_dashboard', array( $this, 'render_staff_dashboard' ) );

        // 4) Restringir Media Library para técnicos (solo sus propias imágenes)
        add_filter(
            'ajax_query_attachments_args',
            array( $this, 'restrict_media_library_for_techs' )
        );
    }

    /**
     * Incluir clases del módulo técnico
     */
    private function includes() {

        // ==========================================
        // 1) Shell/layout
        // ==========================================
        $shell_file = BB_PLUGIN_DIR . 'includes/UI/tech/class-bb-tech-shell.php';
        if ( file_exists( $shell_file ) ) {
            require_once $shell_file;
        } else {
            error_log( 'Bubbles Booking: ❌ No se encontró el shell del técnico en ' . $shell_file );
        }

        // ==========================================
        // 2) Dominio: disponibilidad del técnico
        // ==========================================
        $repo_file_avail    = BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php';
        $service_file_avail = BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityService.php';

        if ( file_exists( $repo_file_avail ) ) {
            require_once $repo_file_avail;
        } else {
            error_log( 'Bubbles Booking: ❌ No se encontró TechAvailabilityRepo en ' . $repo_file_avail );
        }

        if ( file_exists( $service_file_avail ) ) {
            require_once $service_file_avail;
        } else {
            error_log( 'Bubbles Booking: ❌ No se encontró TechAvailabilityService en ' . $service_file_avail );
        }

        // ==========================================
        // 3) Dominio: perfil del técnico
        // ==========================================
        $repo_file_profile    = BB_PLUGIN_DIR . 'includes/domain/tech/profile/TechProfileRepo.php';
        $service_file_profile = BB_PLUGIN_DIR . 'includes/domain/tech/profile/TechProfileService.php';

        if ( file_exists( $repo_file_profile ) ) {
            require_once $repo_file_profile;
        } else {
            error_log( 'Bubbles Booking: ❌ No se encontró TechProfileRepo en ' . $repo_file_profile );
        }

        if ( file_exists( $service_file_profile ) ) {
            require_once $service_file_profile;
        } else {
            error_log( 'Bubbles Booking: ❌ No se encontró TechProfileService en ' . $service_file_profile );
        }

        // ==========================================
        // 4) Dominio: portfolio del técnico (fotos)
        // ==========================================
        $repo_file_portfolio    = BB_PLUGIN_DIR . 'includes/domain/tech/portfolio/TechPortfolioRepo.php';
        $service_file_portfolio = BB_PLUGIN_DIR . 'includes/domain/tech/portfolio/TechPortfolioService.php';

        if ( file_exists( $repo_file_portfolio ) ) {
            require_once $repo_file_portfolio;
        } else {
            error_log( 'Bubbles Booking: ❌ No se encontró TechPortfolioRepo en ' . $repo_file_portfolio );
        }

        if ( file_exists( $service_file_portfolio ) ) {
            require_once $service_file_portfolio;
        } else {
            error_log( 'Bubbles Booking: ❌ No se encontró TechPortfolioService en ' . $service_file_portfolio );
        }

        // 👇 Ya no incluimos Bubbles_Tech_Availability legacy.
    }

    /**
     * Instanciar clases internas
     */
    private function init_classes() {

        // ===========================
        // 1) Dominio: disponibilidad
        // ===========================
        if ( class_exists( 'TechAvailabilityRepo' ) && class_exists( 'TechAvailabilityService' ) ) {

            $repo = new TechAvailabilityRepo();

            // El Service maneja el POST (handle_form en init)
            $this->availability_service = new TechAvailabilityService( $repo );

        } else {
            error_log( 'Bubbles Booking: ❌ No se pudieron cargar TechAvailabilityRepo / TechAvailabilityService.' );
        }

        // ===========================
        // 2) Dominio: perfil
        // ===========================
        if ( class_exists( 'TechProfileRepo' ) && class_exists( 'TechProfileService' ) ) {

            $profile_repo          = new TechProfileRepo();
            $this->profile_service = new TechProfileService( $profile_repo );

        } else {
            error_log( 'Bubbles Booking: ❌ No se pudieron cargar TechProfileRepo / TechProfileService.' );
        }

        // ===========================
        // 3) Dominio: portfolio (fotos técnico)
        // ===========================
        if ( class_exists( 'TechPortfolioRepo' ) && class_exists( 'TechPortfolioService' ) ) {

            $portfolio_repo          = new TechPortfolioRepo();
            $this->portfolio_service = new TechPortfolioService( $portfolio_repo );

        } else {
            error_log( 'Bubbles Booking: ❌ No se pudieron cargar TechPortfolioRepo / TechPortfolioService.' );
        }

        // ===========================
        // 4) Shell/layout principal
        // ===========================
      // ===========================


// (Opcional) pequeño log para ver qué hay
error_log(
    'BB_Tech_Module DEBUG: '
    . 'shell_class=' . ( class_exists( 'BB_Tech_Shell' ) ? 'yes' : 'no' )
    . ' | availability=' . ( $this->availability_service instanceof TechAvailabilityService ? 'yes' : 'no' )
    . ' | profile=' . ( $this->profile_service instanceof TechProfileService ? 'yes' : 'no' )
    . ' | portfolio=' . ( $this->portfolio_service instanceof TechPortfolioService ? 'yes' : 'no' )
);

if (
    class_exists( 'BB_Tech_Shell' )
    && $this->availability_service instanceof TechAvailabilityService
    && $this->profile_service      instanceof TechProfileService
    // 👈 ya NO exigimos que $this->portfolio_service sea instancia
) {
    $this->shell = new BB_Tech_Shell(
        $this->availability_service,
        $this->profile_service,
        $this->portfolio_service // puede venir null
    );
} else {
    error_log( 'Bubbles Booking: ❌ No se pudo instanciar BB_Tech_Shell (faltan servicios o clase).' );
}

    }

    /**
     * Shortcode [bb_staff_dashboard]
     */
    public function render_staff_dashboard( $atts = array(), $content = '' ) {

        // Necesario para usar wp.media (Media Library) en el frontend
        if ( function_exists( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }

        // Encolamos CSS/JS del módulo técnico cuando se usa el shortcode
        wp_enqueue_style(
            'bb-tech-dashboard',
            BB_PLUGIN_URL . 'assets/css/tech/tech.css',
            array(),
            file_exists( BB_PLUGIN_DIR . 'assets/css/tech/tech.css' )
                ? filemtime( BB_PLUGIN_DIR . 'assets/css/tech/tech.css' )
                : '1.0.0'
        );

        wp_enqueue_script(
            'bb-tech-dashboard',
            BB_PLUGIN_URL . 'assets/js/tech/tech.js',
            array( 'jquery' ),
            file_exists( BB_PLUGIN_DIR . 'assets/js/tech/tech.js' )
                ? filemtime( BB_PLUGIN_DIR . 'assets/js/tech/tech.js' )
                : '1.0.0',
            true
        );

        ob_start();

        echo '<!-- bb_staff_dashboard shortcode RUNNING -->';

        // 1) Si NO está logueado → mensaje y link al login
        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( get_permalink() );

            echo '<p>You must be logged in to view this page. 
                        <a href="' . esc_url( $login_url ) . '">Log in</a>
                  </p>';

            return ob_get_clean();
        }

        // 2) Verificar rol: STAFF (bb_staff) o administrador
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;

        if ( ! array_intersect( array( 'bb_staff', 'administrator' ), $roles ) ) {
            echo '<p>You do not have permission to access this dashboard.</p>';
            return ob_get_clean();
        }

        // 3) Renderizar shell/layout
        if ( $this->shell instanceof BB_Tech_Shell ) {
            $this->shell->render( $user );
        } else {
            echo '<p>Shell not initialized. Please check BB_Tech_Shell class.</p>';
        }

        return ob_get_clean();
    }

    /**
     * Restringe la Media Library para técnicos:
     * - Admin ve todo
     * - Técnicos (rol bb_staff) solo ven sus propios adjuntos
     */
    public function restrict_media_library_for_techs( $query ) {

        // Si es admin, no tocamos nada
        if ( current_user_can( 'manage_options' ) ) {
            return $query;
        }

        $user = wp_get_current_user();
        if ( ! $user || ! $user->ID ) {
            return $query;
        }

        $roles = (array) $user->roles;

        // Si el usuario es técnico (rol bb_staff)
        if ( in_array( 'bb_staff', $roles, true ) ) {
            $query['author'] = $user->ID;
        }

        return $query;
    }
}
