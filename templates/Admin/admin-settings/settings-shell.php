<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cascarón de la página de Settings con tabs internos.
 *
 * Variables esperadas:
 *  - string $active_tab         (map | stripe | availability)
 *  - string $settings_page_url  (URL base admin.php?page=bubbles-booking-settings)
 *  - string $tab_content        (HTML del tab activo)
 */
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Bubbles Booking – Settings', 'bubbles-booking' ); ?></h1>
    <p><?php esc_html_e( 'Configure the global settings for the Bubbles Booking plugin.', 'bubbles-booking' ); ?></p>

    <?php
    // URLs de los tabs
    $map_url          = add_query_arg( 'tab', 'map', $settings_page_url );
    $stripe_url       = add_query_arg( 'tab', 'stripe', $settings_page_url );
    $availability_url = add_query_arg( 'tab', 'availability', $settings_page_url );
    ?>

    <h2 class="nav-tab-wrapper">

        <a href="<?php echo esc_url( $map_url ); ?>"
           class="nav-tab <?php echo ( $active_tab === 'map' ) ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Google Maps', 'bubbles-booking' ); ?>
        </a>

        <a href="<?php echo esc_url( $stripe_url ); ?>"
           class="nav-tab <?php echo ( $active_tab === 'stripe' ) ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Stripe Payments', 'bubbles-booking' ); ?>
        </a>

        <a href="<?php echo esc_url( $availability_url ); ?>"
           class="nav-tab <?php echo ( $active_tab === 'availability' ) ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Availability Rules', 'bubbles-booking' ); ?>
        </a>

    </h2>

    <div class="bb-settings-tab-content">
        <?php
        /**
         * Aquí se inserta el contenido del tab activo
         * (Map, Stripe o Availability Rules)
         */
        echo $tab_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </div>
</div>
