<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Bubbles_Installer {

    public static function activate() {
        // ==========================
// ✅ Bookings Final (bb_bookings)
// ==========================
require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-booking-repo.php';
if ( class_exists( 'BB_Booking_Repository' ) ) {
    BB_Booking_Repository::create_table();
}

// ==========================
// Availability - Global Rules
// ==========================
require_once BB_PLUGIN_DIR . 'includes/domain/admin/availability-rules/bb-availability-rules-repo.php';
if ( class_exists( 'BB_Availability_Rules_Repo' ) ) {
    BB_Availability_Rules_Repo::create_table();
}

        // ==========================
        // Availability
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php';
        if ( class_exists( 'TechAvailabilityRepo' ) ) {
            TechAvailabilityRepo::create_table();
        }

      

        // ==========================
        // Services catalog (bb_services)
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-repo.php';
        if ( class_exists( 'BB_Services_Repo' ) ) {
            BB_Services_Repo::create_table();
        }

        // ==========================
        // Add-ons catalog (bb_addons)
        // ==========================
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-repo.php';
        if ( class_exists( 'BB_Addons_Repo' ) ) {
            // Constructor crea tabla si hace falta
            new BB_Addons_Repo();
        }

        // ==========================
        // Pricing (Tiers + Service Tier Prices + Vehicle Type -> Tier)
        // ==========================

        // ---- Tiers ----
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/tiers/bb-pricing-tiers-repo.php';
        if ( class_exists( 'BB_Pricing_Tiers_Repo' ) ) {
            BB_Pricing_Tiers_Repo::create_table();
            BB_Pricing_Tiers_Repo::seed_defaults();
        }

     

        // ---- Vehicle Type -> Tier mapping table ----
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/vehicle-type-tiers/bb-vehicle-type-tiers-repo.php';
        if ( class_exists( 'BB_Vehicle_Type_Tiers_Repo' ) ) {
            BB_Vehicle_Type_Tiers_Repo::create_table();
        }

        // ==========================
        // ✅ Job Targets (bb_job_targets)
        // ==========================
        // Ajusta esta ruta si lo tienes en otro folder
        require_once BB_PLUGIN_DIR . 'includes/domain/admin/job-targets/bb-job-targets-repo.php';
        if ( class_exists( 'BB_Job_Targets_Repo' ) ) {
            BB_Job_Targets_Repo::create_table();
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
