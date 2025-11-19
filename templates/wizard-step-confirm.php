<?php
if (!defined('ABSPATH')) exit;

/**
 * Template: Step 6 - Confirm & pay
 *
 * Variables:
 * - $wizard
 * - $vehicle
 * - $package_label
 * - $package_price
 * - $pkg_desc
 * - $addons_sel
 * - $addons_map
 * - $address_typ
 * - $address
 * - $address_ext
 * - $bb_date
 * - $bb_time
 * - $bb_name, $bb_phone, $bb_email, $bb_notes
 */
?>

<h3 class="bb-section-title">Step 6 · Confirm & pay</h3>

<form method="post" class="bb-step-form" novalidate>

<?php
echo $wizard->hidden('bb_step','confirm');
echo $wizard->hidden_vehicle_fields();
echo $wizard->hidden('bb_package', $wizard->posted('bb_package'));
echo $wizard->hidden_addons_fields();
echo $wizard->hidden_address_fields();
echo $wizard->hidden_date_fields();
?>

<!-- Contact -->
<div class="bb-card">
    <h4>Contact details</h4>

    <p><label>Full name<br>
        <input type="text" name="bb_name" value="<?php echo esc_attr($bb_name); ?>" required>
    </label></p>

    <p><label>Mobile<br>
        <input type="tel" name="bb_phone" value="<?php echo esc_attr($bb_phone); ?>" required>
    </label></p>

    <p><label>Email<br>
        <input type="email" name="bb_email" value="<?php echo esc_attr($bb_email); ?>" required>
    </label></p>

    <p><label>Notes<br>
        <textarea name="bb_notes" rows="3"><?php echo esc_textarea($bb_notes); ?></textarea>
    </label></p>
</div>

<!-- Summary -->
<div class="bb-card">
    <h4>Booking summary</h4>

    <p><strong>Vehicle:</strong> <?php echo esc_html(trim($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model'])); ?></p>

    <p><strong>Package:</strong> 
        <?php echo esc_html($package_label); ?>
        <?php if ($package_price): ?>
            — <?php echo esc_html($package_price); ?>
        <?php endif; ?>
    </p>

    <?php if (!empty($pkg_desc)): ?>
        <p><em><?php echo esc_html($pkg_desc); ?></em></p>
    <?php endif; ?>

    <?php if (!empty($addons_sel)): ?>
        <p><strong>Add-ons:</strong>
            <?php
            $names = array();
            foreach ($addons_sel as $slug) {
                $names[] = $addons_map[$slug] ?? $slug;
            }
            echo esc_html(implode(', ', $names));
            ?>
        </p>
    <?php endif; ?>

    <p><strong>Address:</strong> <?php echo esc_html($address); ?></p>

    <?php if ($address_ext): ?>
        <p><strong>Details:</strong> <?php echo esc_html($address_ext); ?></p>
    <?php endif; ?>

    <p><strong>Date:</strong> <?php echo esc_html($bb_date); ?></p>
    <p><strong>Time:</strong> <?php echo esc_html($bb_time); ?></p>
</div>

<div class="bb-actions">
    <!-- Back: solo navega al paso anterior -->
    <button type="submit"
            name="bb_back"
            value="1"
            class="bb-btn bb-btn-secondary">
        &laquo; Back
    </button>

    <!-- Confirm & pay: dispara bb_confirm_booking -->
    <button type="submit"
            name="bb_confirm_booking"
            value="1"
            class="bb-btn bb-btn-primary">
        Confirm &amp; pay &raquo;
    </button>
</div>

</form>
