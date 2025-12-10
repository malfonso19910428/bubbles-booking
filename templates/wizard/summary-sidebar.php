<?php
if (!defined('ABSPATH')) exit;

/** @var Bubbles_Summary $summary */

$vehicles_detailed = $summary->get_detailed_vehicles();
$addr              = $summary->get_address();
$date              = $summary->get_date();
$time              = $summary->get_time();
$totals            = $summary->get_totals();
?>

<aside class="bb-summary">

    <h3 class="bb-summary-title">Your booking</h3>

    <?php if (!empty($totals['grand_total'])): ?>
        <div class="bb-summary-total-top">
            <span class="bb-summary-total-label">Estimated total</span>
            <span class="bb-summary-total-amount">
                $<?php echo esc_html( number_format( (float) $totals['grand_total'], 2 ) ); ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- VEHICLES + PACKAGE + ADD-ONS POR VEHÍCULO -->
    <div class="bb-summary-section">
        <h4>Vehicles</h4>

        <?php if (!empty($vehicles_detailed)): ?>
            <?php foreach ($vehicles_detailed as $veh): ?>
                <div class="bb-summary-vehicle">
                    <strong class="bb-summary-main">
                        <?php echo esc_html( $veh['title'] ); ?>
                    </strong>

                    <?php if (!empty($veh['package_label'])): ?>
                        <div class="bb-summary-line">
                            <span class="bb-summary-label">Package:</span>
                            <span class="bb-summary-value">
                                <?php echo esc_html( $veh['package_label'] ); ?>
                                <?php if ((float)$veh['package_price'] > 0): ?>
                                    · $<?php echo esc_html( number_format( (float)$veh['package_price'], 2 ) ); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="bb-summary-line">
                        <span class="bb-summary-label">Add-ons:</span>

                        <?php if (!empty($veh['addons_detail'])): ?>
                            <ul class="bb-summary-list">
                                <?php foreach ($veh['addons_detail'] as $ad): ?>
                                    <li>
                                        <?php echo esc_html( $ad['name'] ); ?>
                                        <?php if ((float)$ad['price'] > 0): ?>
                                            · $<?php echo esc_html( number_format( (float)$ad['price'], 2 ) ); ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="bb-summary-value">No add-ons selected.</span>
                        <?php endif; ?>
                    </div>

                    <div class="bb-summary-line bb-summary-subtotal">
                        <span class="bb-summary-label">Subtotal:</span>
                        <span class="bb-summary-value">
                            $<?php echo esc_html( number_format( (float)$veh['subtotal'], 2 ) ); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No vehicles yet.</p>
        <?php endif; ?>
    </div>

    <!-- ADDRESS -->
    <div class="bb-summary-section">
        <h4>Address</h4>
        <?php if (!empty($addr['address'])): ?>
            <p><?php echo esc_html($addr['address']); ?></p>
            <?php if (!empty($addr['extra'])): ?>
                <p class="bb-summary-notes">
                    <?php echo esc_html($addr['extra']); ?>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p>No address yet.</p>
        <?php endif; ?>
    </div>

    <!-- DATE & TIME -->
    <div class="bb-summary-section">
        <h4>Date &amp; time</h4>
        <?php if (!empty($date) && !empty($time)): ?>
            <p><?php echo esc_html( $date . ' · ' . $time ); ?></p>
        <?php else: ?>
            <p>No date selected.</p>
        <?php endif; ?>
    </div>

</aside>
