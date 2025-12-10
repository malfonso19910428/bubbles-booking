<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Thank you / Booking confirmed
 *
 * Depende de:
 *  - $state con el resumen completo del wizard
 *  - Variables básicas (bb_name, bb_phone, bb_email, final_price, etc.)
 */

// ---------------------- STATE & RESUMEN GLOBAL ----------------------
$state = ( isset( $state ) && is_array( $state ) ) ? $state : array();

$bb_summary = ( isset( $state['summary'] ) && is_array( $state['summary'] ) )
    ? $state['summary']
    : array();

$totals = ( isset( $bb_summary['totals'] ) && is_array( $bb_summary['totals'] ) )
    ? $bb_summary['totals']
    : array();

// ---------------------- TOTAL FINAL ----------------------
// Preferimos el grand_total del summary; si no, usamos final_price del controlador.
if ( isset( $totals['grand_total'] ) ) {
    $display_price = (float) $totals['grand_total'];
} else {
    $display_price = isset( $final_price ) ? (float) $final_price : 0.0;
}

$final_display = '$' . number_format_i18n( $display_price, 2 );

// ---------------------- CONTACTO ----------------------
$contact = ( isset( $state['contact'] ) && is_array( $state['contact'] ) )
    ? $state['contact']
    : array();

$bb_name  = isset( $bb_name )  ? $bb_name  : ( $contact['name']  ?? '' );
$bb_phone = isset( $bb_phone ) ? $bb_phone : ( $contact['phone'] ?? '' );
$bb_email = isset( $bb_email ) ? $bb_email : ( $contact['email'] ?? '' );

// ---------------------- FECHA / HORA / DIRECCIÓN ----------------------
$bb_date = isset( $bb_date ) ? $bb_date : ( $state['date'] ?? '' );
$bb_time = isset( $bb_time ) ? $bb_time : ( $state['time'] ?? '' );

$address_state = ( isset( $state['address'] ) && is_array( $state['address'] ) )
    ? $state['address']
    : array();

$address       = isset( $address )       ? $address       : ( $address_state['address'] ?? '' );
$address_extra = isset( $address_extra ) ? $address_extra : ( $address_state['extra']   ?? '' );

// ---------------------- LISTA DE VEHÍCULOS (MULTI) ----------------------
$detailed_vehicles = array();

// 1) Preferimos el resumen detallado de Bubbles_Summary (multi-vehículo)
if ( ! empty( $bb_summary['detailed_vehicles'] ) && is_array( $bb_summary['detailed_vehicles'] ) ) {

    $detailed_vehicles = $bb_summary['detailed_vehicles'];

} elseif ( ! empty( $state['vehicles'] ) && is_array( $state['vehicles'] ) ) {

    // 2) Fallback: usamos state['vehicles'] tal cual
    foreach ( $state['vehicles'] as $v ) {
        $detailed_vehicles[] = array(
            'year'    => $v['year']    ?? '',
            'make'    => $v['make']    ?? '',
            'model'   => $v['model']   ?? '',
            'package' => $v['package'] ?? '',
            'addons'  => $v['addons']  ?? array(),
        );
    }

} else {

    // 3) Último recurso: un solo vehículo desde POST (por compatibilidad)
    $car_year  = isset( $car_year )  ? $car_year  : ( $_POST['car_year']  ?? '' );
    $car_make  = isset( $car_make )  ? $car_make  : ( $_POST['car_make']  ?? '' );
    $car_model = isset( $car_model ) ? $car_model : ( $_POST['car_model'] ?? '' );

    $detailed_vehicles[] = array(
        'year'    => $car_year,
        'make'    => $car_make,
        'model'   => $car_model,
        'package' => isset( $package_label ) ? $package_label : ( $state['package'] ?? '' ),
        'addons'  => ( isset( $_POST['addons'] ) && is_array( $_POST['addons'] ) )
            ? array_map( 'sanitize_text_field', $_POST['addons'] )
            : array(),
    );
}

// URL para el botón "Start over" (ajusta el slug si tu página es otra)
$start_over_url = home_url( '/pricing/' );
?>

<div class="bb-thankyou-wrapper">
    <div class="bb-thankyou-card">

        <!-- HEADER -->
        <div class="bb-thankyou-header">
            <div class="bb-thankyou-icon-circle">
                <span class="bb-thankyou-icon-check">&#10003;</span>
            </div>
            <h3 class="bb-thankyou-title">
                <?php esc_html_e( 'Thank you! Your booking is confirmed.', 'bubbles-booking' ); ?>
            </h3>
            <p class="bb-thankyou-subtitle">
                <?php esc_html_e(
                    'We received your booking and payment details. A confirmation message will be sent shortly.',
                    'bubbles-booking'
                ); ?>
            </p>
        </div>

        <!-- TOTAL -->
        <div class="bb-thankyou-total">
            <div class="bb-thankyou-total-label">
                <?php esc_html_e( 'Order total', 'bubbles-booking' ); ?>
            </div>
            <div class="bb-thankyou-total-amount">
                <?php echo esc_html( $final_display ); ?>
            </div>
            <div class="bb-thankyou-total-help">
                <?php esc_html_e( 'This is the amount associated with your booking.', 'bubbles-booking' ); ?>
            </div>
        </div>

        <!-- GRID: CUSTOMER + SERVICE -->
        <div class="bb-thankyou-grid">

            <!-- CUSTOMER DETAILS -->
            <section>
                <h4 class="bb-thankyou-section-title">
                    <?php esc_html_e( 'Customer details', 'bubbles-booking' ); ?>
                </h4>

                <ul class="bb-thankyou-list">
                    <?php if ( $bb_name ) : ?>
                        <li class="bb-thankyou-list-item">
                            <strong><?php esc_html_e( 'Name:', 'bubbles-booking' ); ?></strong>
                            <?php echo ' ' . esc_html( $bb_name ); ?>
                        </li>
                    <?php endif; ?>

                    <?php if ( $bb_phone ) : ?>
                        <li class="bb-thankyou-list-item">
                            <strong><?php esc_html_e( 'Phone:', 'bubbles-booking' ); ?></strong>
                            <?php echo ' ' . esc_html( $bb_phone ); ?>
                        </li>
                    <?php endif; ?>

                    <?php if ( $bb_email ) : ?>
                        <li class="bb-thankyou-list-item">
                            <strong><?php esc_html_e( 'Email:', 'bubbles-booking' ); ?></strong>
                            <?php echo ' ' . esc_html( $bb_email ); ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </section>

            <!-- SERVICE DETAILS – MISMA ESTRUCTURA QUE CUSTOMER -->
            <section>
                <h4 class="bb-thankyou-section-title">
                    <?php esc_html_e( 'Service details', 'bubbles-booking' ); ?>
                </h4>

                <ul class="bb-thankyou-list">

                    <?php if ( ! empty( $detailed_vehicles ) ) : ?>
                        <?php foreach ( $detailed_vehicles as $idx => $v ) :
                            $vy      = $v['year']    ?? '';
                            $vmk     = $v['make']    ?? '';
                            $vmd     = $v['model']   ?? '';
                            $vpkg    = $v['package'] ?? '';
                            $vaddons = $v['addons']  ?? array();
                        ?>
                            <!-- Vehicle X -->
                            <li class="bb-thankyou-list-item">
                                <strong>
                                    <?php printf( esc_html__( 'Vehicle %d:', 'bubbles-booking' ), $idx + 1 ); ?>
                                </strong>
                                <?php echo ' ' . esc_html( trim( "$vy $vmk $vmd" ) ); ?>
                            </li>

                            <?php if ( $vpkg ) : ?>
                                <li class="bb-thankyou-list-item" style="margin-left:1.25rem;">
                                    <strong><?php esc_html_e( 'Package:', 'bubbles-booking' ); ?></strong>
                                    <?php echo ' ' . esc_html( $vpkg ); ?>
                                </li>
                            <?php endif; ?>

                            <?php if ( ! empty( $vaddons ) ) : ?>
                                <li class="bb-thankyou-list-item" style="margin-left:1.25rem;">
                                    <strong><?php esc_html_e( 'Add-ons:', 'bubbles-booking' ); ?></strong>
                                    <?php echo ' ' . esc_html( implode( ', ', $vaddons ) ); ?>
                                </li>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ( $bb_date || $bb_time ) : ?>
                        <li class="bb-thankyou-list-item">
                            <strong><?php esc_html_e( 'Service date & time:', 'bubbles-booking' ); ?></strong>
                            <?php echo ' ' . esc_html( trim( "$bb_date $bb_time" ) ); ?>
                        </li>
                    <?php endif; ?>

                    <?php if ( $address ) : ?>
                        <li class="bb-thankyou-list-item">
                            <strong><?php esc_html_e( 'Address:', 'bubbles-booking' ); ?></strong>
                            <?php echo ' ' . esc_html( $address ); ?>
                            <?php if ( $address_extra ) : ?>
                                <br><?php echo esc_html( $address_extra ); ?>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>

                </ul>
            </section>

        </div><!-- /.bb-thankyou-grid -->

        <!-- WHAT HAPPENS NEXT -->
        <div class="bb-thankyou-next">
            <h4 class="bb-thankyou-section-title">
                <?php esc_html_e( 'What happens next?', 'bubbles-booking' ); ?>
            </h4>
            <ul class="bb-thankyou-next-list">
                <li><?php esc_html_e( 'We will review the details of your service.', 'bubbles-booking' ); ?></li>
                <li><?php esc_html_e( 'You will receive a confirmation message shortly.', 'bubbles-booking' ); ?></li>
                <li><?php esc_html_e( 'Our team will arrive at the scheduled time.', 'bubbles-booking' ); ?></li>
            </ul>
        </div>

        <!-- BOTÓN ÚNICO: VOLVER AL PRINCIPIO DEL WIZARD -->
        <div class="bb-thankyou-actions">
            <a href="<?php echo esc_url( $start_over_url ); ?>" class="bb-btn bb-btn-primary">
                &laquo; <?php esc_html_e( 'Start over', 'bubbles-booking' ); ?>
            </a>
        </div>

    </div><!-- /.bb-thankyou-card -->
</div><!-- /.bb-thankyou-wrapper -->
