<?php
// FILE: includes/domain/availability-engine/BB_Availability_Engine_Factory.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Factory: BB_Availability_Engine
 *
 * Construye el engine usando:
 * - Rules (global)
 * - Draft Repo + Draft Service (el engine crea DraftService por token)
 * - Weekly availability repo
 * - DurationService (service + addons)
 */
final class BB_Availability_Engine_Factory {

    /** @var BB_Availability_Engine|null */
    private static ?BB_Availability_Engine $engine = null;

    public static function make(): BB_Availability_Engine {

        if ( self::$engine instanceof BB_Availability_Engine ) {
            return self::$engine;
        }

        // ==========================
        // Rules
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-service.php';

        // ==========================
        // Draft (repo + service)
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-service.php';

        // ==========================
        // Tech weekly availability repo
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php';

        // ==========================
        // Catalog: Services + Addons (duration)
        // ⚠️ Ajusta estas rutas si tus archivos viven en otra carpeta
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/services/bb-services-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/services/bb-services-service.php';

        require_once BB_PLUGIN_DIR . 'includes/domain/addons/bb-addons-repo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/addons/bb-addons-service.php';

        // Duration service
        require_once BB_PLUGIN_DIR . 'includes/domain/availability-engine/bb-duration-service.php';

        // Engine
        require_once BB_PLUGIN_DIR . 'includes/domain/availability-engine/bb-availability-engine.php';

        // ==========================
        // Instances
        // ==========================
        $rules_repo    = new BB_Availability_Rules_Repo();
        $rules_service = new BB_Availability_Rules_Service( $rules_repo );

        $draft_repo = new BB_Draft_Steps_Repo();

        $tech_availability_repo = new TechAvailabilityRepo();

        $services_repo    = new BB_Services_Repo();
        $services_service = new BB_Services_Service( $services_repo );

        $addons_repo    = new BB_Addons_Repo();
        $addons_service = new BB_Addons_Service( $addons_repo );

        $duration_service = new BB_Duration_Service( $services_service, $addons_service );

        self::$engine = new BB_Availability_Engine(
            $rules_service,
            $tech_availability_repo,
            $draft_repo,
            $duration_service
        );

        return self::$engine;
    }
}
