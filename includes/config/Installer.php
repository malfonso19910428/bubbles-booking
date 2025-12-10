<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Bubbles_Installer {

    public static function activate() {

        // ==========================
        // Availability
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php';
        if ( class_exists( 'TechAvailabilityRepo' ) ) {
            TechAvailabilityRepo::create_table();
        }

        // ==========================
        // Tech Portfolio (fotos)
        // ==========================
       // require_once BB_PLUGIN_DIR . 'includes/domain/tech/portfolio/TechPortfolioRepo.php';
       // if ( class_exists( 'TechPortfolioRepo' ) ) {
       //     TechPortfolioRepo::create_table();
       // }

        // ==========================
        // Services catalog (bb_services)
        // ==========================
           // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/catalog/services/bb-services-repo.php';
        BB_Services_Repo::create_table();

        // ==========================
        // Add-ons catalog (bb_addons)
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/catalog/addons/bb-addons-repo.php';

        if ( class_exists( 'BB_Addons_Repo' ) ) {
            // El constructor de BB_Addons_Repo llama a maybe_create_table()
            new BB_Addons_Repo();
    }
}
}