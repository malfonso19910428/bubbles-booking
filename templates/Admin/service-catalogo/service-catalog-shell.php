<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Catalog Shell
 *
 * Variables:
 *  - string $bb_current_tab        ('services' | 'addons')
 *  - array  $bb_services_list
 *  - array  $bb_addons_list
 *  - bool   $bb_service_created
 *  - bool   $bb_addon_created
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
    $base_url = remove_query_arg( array( 'bb_tab' ) );
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
    </h2>

    <?php
    // Incluir la vista específica de la tab
    if ( $bb_current_tab === 'services' ) {
        $bb_services_list_local = $bb_services_list;
        include BB_PLUGIN_DIR . 'templates/Admin/service-catalogo/services.php';
    } else {
        $bb_addons_list_local = $bb_addons_list;
        include BB_PLUGIN_DIR . 'templates/Admin/service-catalogo/addons.php';
    }
    ?>
</div>
