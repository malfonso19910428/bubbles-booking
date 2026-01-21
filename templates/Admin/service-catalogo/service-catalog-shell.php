<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Catalog Shell
 *
 * Variables:
 *  - string $bb_current_tab        ('services' | 'addons' | 'pricing' | 'job_targets')
 *  - array  $bb_services_list
 *  - array  $bb_addons_list
 *  - bool   $bb_service_created
 *  - bool   $bb_addon_created
 *  - array  $bb_pricing_view       (data real de pricing)
 *  - array  $bb_job_targets_view   (data de job targets)
 */
?>
<div class="wrap">
    <h1>Bubbles Booking – Catalog</h1>
    <p>Manage the services and add-ons available in the booking wizard.</p>

    <?php if ( ! empty( $bb_service_created ) && $bb_current_tab === 'services' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Service created successfully.</p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $bb_addon_created ) && $bb_current_tab === 'addons' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Add-on created successfully.</p>
        </div>
    <?php endif; ?>

    <?php
    // ✅ Base URL FIJO para tabs (evita perder page=bb-catalog y volver a Services)
    $base_url = admin_url( 'admin.php?page=bb-catalog' );
    ?>

    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url( add_query_arg( 'bb_tab', 'services', $base_url ) ); ?>"
           class="nav-tab <?php echo ( $bb_current_tab === 'services' ) ? 'nav-tab-active' : ''; ?>">
            Services
        </a>

        <a href="<?php echo esc_url( add_query_arg( 'bb_tab', 'addons', $base_url ) ); ?>"
           class="nav-tab <?php echo ( $bb_current_tab === 'addons' ) ? 'nav-tab-active' : ''; ?>">
            Add-ons
        </a>

        <a href="<?php echo esc_url( add_query_arg( 'bb_tab', 'job_targets', $base_url ) ); ?>"
           class="nav-tab <?php echo ( $bb_current_tab === 'job_targets' ) ? 'nav-tab-active' : ''; ?>">
            Job Targets
        </a>

        <a href="<?php echo esc_url( add_query_arg( 'bb_tab', 'pricing', $base_url ) ); ?>"
           class="nav-tab <?php echo ( $bb_current_tab === 'pricing' ) ? 'nav-tab-active' : ''; ?>">
            Pricing
        </a>
    </h2>

    <?php
    // Incluir la vista específica de la tab
    if ( $bb_current_tab === 'services' ) {

        $bb_services_list_local = $bb_services_list;
        include BB_PLUGIN_DIR . 'templates/Admin/service-catalogo/services.php';

    } elseif ( $bb_current_tab === 'addons' ) {

        $bb_addons_list_local = $bb_addons_list;
        include BB_PLUGIN_DIR . 'templates/Admin/service-catalogo/addons.php';

    } elseif ( $bb_current_tab === 'job_targets' ) {

        // ✅ Job Targets: la vista usa $view (view model)
        $view = ( isset( $bb_job_targets_view ) && is_array( $bb_job_targets_view ) )
            ? $bb_job_targets_view
            : array();

        include BB_PLUGIN_DIR . 'templates/Admin/service-catalogo/job-targets.php';

    } else {

        // Pricing: data real de pricing
        $bb_pricing_view_local = ( isset( $bb_pricing_view ) && is_array( $bb_pricing_view ) )
            ? $bb_pricing_view
            : array();

        // ✅ Si tu pricing.php espera $bb_pricing_view
        $bb_pricing_view = $bb_pricing_view_local;

        include BB_PLUGIN_DIR . 'templates/Admin/service-catalogo/pricing.php';
    }
    ?>
</div>
