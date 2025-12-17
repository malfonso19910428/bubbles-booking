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
        // Tech Portfolio (fotos) - (opcional / aún comentado)
        // ==========================
        /*
        require_once BB_PLUGIN_DIR . 'includes/domain/tech/portfolio/TechPortfolioRepo.php';
        if ( class_exists( 'TechPortfolioRepo' ) ) {
            TechPortfolioRepo::create_table();
        }
        */

        // ==========================
        // Services catalog (bb_services)
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/catalog/services/bb-services-repo.php';
        if ( class_exists( 'BB_Services_Repo' ) ) {
            BB_Services_Repo::create_table();
        }

        // ==========================
        // Add-ons catalog (bb_addons)
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/catalog/addons/bb-addons-repo.php';
        if ( class_exists( 'BB_Addons_Repo' ) ) {
            // Constructor crea tabla si hace falta
            new BB_Addons_Repo();
        }

        // ==========================
        // Wizard Draft Steps (bb_booking_drafts)
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-repo.php';
        if ( class_exists( 'BB_Draft_Steps_Repo' ) ) {
            $repo = new BB_Draft_Steps_Repo();
            $repo->create_table_if_needed();
        }
    }
}
