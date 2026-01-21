<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Factory: BB_Availability_Engine
 *
 * Responsable de construir el Availability Engine
 * con todas sus dependencias correctamente inyectadas.
 */
class BB_Availability_Engine_Factory {

    /**
     * Crea una instancia completamente funcional del engine.
     */
    public static function make(): BB_Availability_Engine {

        // ==========================
        // Reglas globales
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-service.php';

        $rules_repo    = new BB_Availability_Rules_Repo();
        $rules_service = new BB_Availability_Rules_Service( $rules_repo );

        // ==========================
        // Draft del wizard
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-repo.php';
        $draft_repo = new BB_Draft_Steps_Repo();

        // ==========================
        // Disponibilidad base de técnicos
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php';
        $tech_availability_repo = new TechAvailabilityRepo();

        // ==========================
        // Bookings existentes
        // ==========================
        // ⚠️ Ajusta este repo cuando lo tengas definido
        require_once BB_PLUGIN_DIR . 'includes/domain/bookings/bb-bookings-repo.php';
        $bookings_repo = new BB_Bookings_Repo();

        // ==========================
        // Engine
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/availability/bb-availability-engine.php';

        return new BB_Availability_Engine(
            $rules_service,
            $tech_availability_repo,
            $bookings_repo,
            $draft_repo
        );
    }
}
