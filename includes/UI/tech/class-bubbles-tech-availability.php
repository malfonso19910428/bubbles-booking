<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Controladora de la vista "My Availability"
 *
 * - No guarda directamente (eso lo hace TechAvailabilityService)
 * - Solo se encarga de preparar datos para la plantilla
 *   templates/tech/availability.php
 */
class Bubbles_Tech_Availability {

    /**
     * @var TechAvailabilityService|null
     */
    private $service;

    /**
     * Constructor.
     *
     * Acepta el service por inyección. Lo dejamos opcional para no romper
     * ningún new Bubbles_Tech_Availability() que exista por ahí.
     */
    public function __construct( TechAvailabilityService $service = null ) {
        $this->service = $service;
    }

    /**
     * Permite inyectar el service después de instanciar, si hace falta.
     */
    public function set_service( TechAvailabilityService $service ) {
        $this->service = $service;
    }

    /**
     * Renderiza la página "My availability" del técnico.
     *
     * - Obtiene la disponibilidad semanal del técnico actual
     * - Pasa la variable $bb_weekly_availability a la plantilla
     * - Incluye templates/tech/availability.php
     *
     * @param WP_User|null $user (opcional) técnico actual
     */
    public function render_page( $user = null ) {

        if ( ! $this->service instanceof TechAvailabilityService ) {
            echo '<p>Availability service not initialized.</p>';
            return;
        }

        if ( ! $user instanceof WP_User ) {
            $user = wp_get_current_user();
        }

        // Usuario disponible en la vista, por si lo necesitas
        $bb_current_user = $user;

        // Obtenemos la disponibilidad semanal desde el service
        // (el service usa el usuario logueado internamente)
        $bb_weekly_availability = $this->service->week_for_user();

        // Plantilla de disponibilidad
        $template = BB_PLUGIN_DIR . 'templates/tech/availability.php';

        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<p>Availability template not found: <code>' . esc_html( $template ) . '</code></p>';
        }
    }
}
