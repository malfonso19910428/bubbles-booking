<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! function_exists('bb_pricing_engine') ) {

function bb_pricing_engine(): BB_Pricing_Engine {

    static $instance = null;
    if ( $instance instanceof BB_Pricing_Engine ) {
        return $instance;
    }

    // Services
    require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-repo.php';
    require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-service.php';

    // Job Targets
    require_once BB_PLUGIN_DIR . 'includes/domain/admin/job-targets/bb-job-targets-repo.php';
    require_once BB_PLUGIN_DIR . 'includes/domain/admin/job-targets/bb-job-targets-service.php';

    // Pricing Tiers (default_add)
    require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/tiers/bb-pricing-tiers-repo.php';
    require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/tiers/bb-pricing-tiers-service.php';

    // Service ↔ Tier overrides (optional)
  // require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/tiers/bb-service-tier-prices-rep.php';
   // require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/tiers/bb-service-tier-prices-service.php';

    // Engine
    require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/bb-pricing-engine.php';

    $services_service = new BB_Services_Service( new BB_Services_Repo() );

    $job_targets_service = new BB_Job_Targets_Service(
        new BB_Job_Targets_Repo()
    );

   // $service_tier_prices_service = new BB_Service_Tier_Prices_Service(
    //   new BB_Service_Tier_Prices_Repo()
   // );

    $pricing_tiers_service = new BB_Pricing_Tiers_Service(
        new BB_Pricing_Tiers_Repo()
    );

    // ✅ MATCH con el constructor nuevo (4 args, sin vehicle_type_tiers)
    $instance = new BB_Pricing_Engine(
        $services_service,
        $job_targets_service,
       // $service_tier_prices_service,
        $pricing_tiers_service
    );

    return $instance;
}

}
