<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shell/layout del dashboard técnico
 *
 * - Decide la vista actual (overview, profile, availability, schedule, jobs, portfolio)
 * - Pasa datos a la plantilla principal:
 *   templates/tech/tech-shell.php
 *
 * Variables que verá la plantilla:
 *   - $bb_current_user            (WP_User)
 *   - $bb_current_view            (string)
 *   - $bb_weekly_availability     (array)  // para la vista "availability"
 *   - $bb_profile                 (array)  // para la vista "profile"
 *   - $bb_portfolio_approved      (array)  // para la vista "portfolio" (aprobadas)   🔹 NUEVO
 *   - $bb_portfolio_pending       (array)  // para la vista "portfolio" (pendientes) 🔹 NUEVO
 */
class BB_Tech_Shell {

    /**
     * @var TechAvailabilityService
     */
    protected $availability_service;

    /**
     * @var TechProfileService
     */
    protected $profile_service;

    /**
     * @var TechPortfolioService 🔹 NUEVO
     */
  /**
 * Constructor: recibe los servicios de dominio
 *
 * @param TechAvailabilityService $availability_service
 * @param TechProfileService      $profile_service
 * @param TechPortfolioService|null $portfolio_service
 */
public function __construct(
    TechAvailabilityService $availability_service,
    TechProfileService $profile_service,
    $portfolio_service = null // 👈 ahora es opcional y sin type-hint estricto
) {
    $this->availability_service = $availability_service;
    $this->profile_service      = $profile_service;
    $this->portfolio_service    = $portfolio_service;
}
  protected $portfolio_service;

  

    /**
     * Render de la shell completa
     *
     * @param WP_User $user
     */
    public function render( $user ) {

        // Usuario actual que verá el dashboard (disponible en la plantilla)
        $bb_current_user = $user;

        // Vista actual: ?bb_view=overview|profile|availability|schedule|jobs|portfolio
        $bb_current_view = isset( $_GET['bb_view'] )
            ? sanitize_key( $_GET['bb_view'] )
            : 'overview';

        // Variables que usarán las vistas
        $bb_weekly_availability = array();
        $bb_profile             = array();

        // 🔹 Nuevas variables para portfolio
        $bb_portfolio_approved  = array();
        $bb_portfolio_pending   = array();

        if ( $bb_current_view === 'availability' ) {
            // Slots de disponibilidad desde la DB
            $bb_weekly_availability = $this->availability_service->week_for_user( $bb_current_user->ID );
        }

        if ( $bb_current_view === 'profile' ) {
            // Perfil completo del técnico desde user_meta
            $bb_profile = $this->profile_service->profile_for_user( $bb_current_user->ID );
        }

        if ( $bb_current_view === 'portfolio' ) {
            // 🔹 Portfolio del técnico
            // Aprobadas → visibles al público
            if ( method_exists( $this->portfolio_service, 'get_public_portfolio' ) ) {
                $bb_portfolio_approved = $this->portfolio_service->get_public_portfolio( $bb_current_user->ID );
            }

            // Pendientes → el técnico las ve pero aún no están publicadas
            if ( method_exists( $this->portfolio_service, 'get_pending_for_tech' ) ) {
                $bb_portfolio_pending = $this->portfolio_service->get_pending_for_tech( $bb_current_user->ID );
            }
        }

        // Ruta de la plantilla de shell
        $template = BB_PLUGIN_DIR . 'templates/tech/tech-shell.php';

        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<p>Technical dashboard template not found: <code>' . esc_html( $template ) . '</code></p>';
        }
    }
}
