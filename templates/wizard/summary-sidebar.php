<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$jobs   = isset( $summary['jobs'] ) && is_array( $summary['jobs'] ) ? $summary['jobs'] : array();
$totals = isset( $summary['totals'] ) && is_array( $summary['totals'] ) ? $summary['totals'] : array();

$job = ! empty( $jobs[0] ) && is_array( $jobs[0] ) ? $jobs[0] : array();

$vehicle_label = (string) ( $job['subject']['label'] ?? '' );
$service_name  = (string) ( $job['service']['name'] ?? '' );
$service_price = (float)  ( $job['service']['price'] ?? 0 );

$addons = isset( $job['addons'] ) && is_array( $job['addons'] ) ? $job['addons'] : array();

$addr = isset( $job['address'] ) && is_array( $job['address'] ) ? $job['address'] : array();
$addr_line1 = trim( (string) ( $addr['line1'] ?? '' ) );
$addr_city  = trim( (string) ( $addr['city'] ?? '' ) );
$addr_state = trim( (string) ( $addr['state'] ?? '' ) );
$addr_zip   = trim( (string) ( $addr['zip'] ?? '' ) );

$schedule = isset( $job['schedule'] ) && is_array( $job['schedule'] ) ? $job['schedule'] : array();
$date_label = trim( (string) ( $schedule['date_label'] ?? '' ) );
$slot_label = trim( (string) ( $schedule['slot_label'] ?? '' ) );

/** ✅ NUEVO: customer */
$cust = isset( $job['customer'] ) && is_array( $job['customer'] ) ? $job['customer'] : array();
$cust_name  = trim( (string) ( $cust['name'] ?? '' ) );
$cust_phone = trim( (string) ( $cust['phone'] ?? '' ) );
$cust_email = trim( (string) ( $cust['email'] ?? '' ) );
// Notes normalmente NO lo muestro en sidebar (ruido), pero si quieres lo activamos.
// $cust_notes = trim( (string) ( $cust['notes'] ?? '' ) );

$grand_total = (float) ( $totals['grand_total'] ?? 0 );
?>

<div class="bb-summary-inner">

    <h3 class="bb-summary-title">Summary</h3>

    <div class="bb-summary-block">
        <div class="bb-summary-label">Vehicle</div>
        <div class="bb-summary-value"><?php echo esc_html( $vehicle_label !== '' ? $vehicle_label : '—' ); ?></div>
    </div>

    <div class="bb-summary-block">
        <div class="bb-summary-label">Package</div>
        <div class="bb-summary-value">
            <?php echo esc_html( $service_name !== '' ? $service_name : '—' ); ?>
            <?php if ( $service_price > 0 ) : ?>
                <div class="bb-summary-sub">$<?php echo esc_html( number_format( $service_price, 2 ) ); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! empty( $addons ) ) : ?>
        <div class="bb-summary-block">
            <div class="bb-summary-label">Add-ons</div>
            <div class="bb-summary-value">
                <ul class="bb-summary-list">
                    <?php foreach ( $addons as $a ) :
                        if ( ! is_array( $a ) ) continue;
                        $n = (string) ( $a['name'] ?? '' );
                        $p = (float)  ( $a['price'] ?? 0 );
                    ?>
                        <li>
                            <?php echo esc_html( $n !== '' ? $n : 'Add-on' ); ?>
                            <?php if ( $p > 0 ) : ?>
                                <span class="bb-summary-price">$<?php echo esc_html( number_format( $p, 2 ) ); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="bb-summary-block">
        <div class="bb-summary-label">Address</div>
        <div class="bb-summary-value">
            <?php if ( $addr_line1 !== '' ) : ?>
                <?php echo esc_html( $addr_line1 ); ?><br>
                <?php echo esc_html( trim( $addr_city . ( $addr_state ? ', ' . $addr_state : '' ) . ( $addr_zip ? ' ' . $addr_zip : '' ) ) ); ?>
            <?php else : ?>
                —
            <?php endif; ?>
        </div>
    </div>

    <div class="bb-summary-block">
        <div class="bb-summary-label">Date & Time</div>
        <div class="bb-summary-value">
            <?php if ( $date_label !== '' && $slot_label !== '' ) : ?>
                <?php echo esc_html( $date_label ); ?><br>
                <?php echo esc_html( $slot_label ); ?>
            <?php else : ?>
                —
            <?php endif; ?>
        </div>
    </div>

    <!-- ✅ NUEVO: Customer -->
    <div class="bb-summary-block">
        <div class="bb-summary-label">Customer</div>
        <div class="bb-summary-value">
            <?php if ( $cust_name !== '' || $cust_phone !== '' || $cust_email !== '' ) : ?>
                <?php if ( $cust_name !== '' ) : ?>
                    <?php echo esc_html( $cust_name ); ?><br>
                <?php endif; ?>
                <?php if ( $cust_phone !== '' ) : ?>
                    <?php echo esc_html( $cust_phone ); ?><br>
                <?php endif; ?>
                <?php if ( $cust_email !== '' ) : ?>
                    <?php echo esc_html( $cust_email ); ?>
                <?php endif; ?>
            <?php else : ?>
                —
            <?php endif; ?>
        </div>
    </div>

    <hr>

    <div class="bb-summary-total">
        <span>Total</span>
        <strong>$<?php echo esc_html( number_format( $grand_total, 2 ) ); ?></strong>
    </div>

</div>
