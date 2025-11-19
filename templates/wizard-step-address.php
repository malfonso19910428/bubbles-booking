<?php
if (!defined('ABSPATH')) exit;

/**
 * Template: Step 4 - Address
 */
?>

<h3 class="bb-section-title">Step 4 · Service address</h3>

<?php if (!empty($errors['address'])): ?>
    <p class="bb-error" style="color:#b91c1c;">
        <?php echo esc_html($errors['address']); ?>
    </p>
<?php else: ?>
    <p>Please enter the address where we will work.</p>
<?php endif; ?>

<form method="post" class="bb-step-form bb-step-address" novalidate>
    <!-- Paso actual -->
    <input type="hidden" name="bb_step" value="address">

    <?php
    // Reinyectar datos de pasos anteriores
    echo $wizard->hidden_vehicle_fields();
    echo $wizard->hidden('bb_package', $wizard->posted('bb_package'));
    echo $wizard->hidden_addons_fields();
    echo $wizard->hidden_date_fields();
    echo $wizard->hidden_contact_fields();
    ?>

    <p><strong>Address type</strong><br>
        <label><input type="radio" name="bb_address_type" value="home" <?php checked($address_type,'home'); ?>> Home</label>
        <label><input type="radio" name="bb_address_type" value="work" <?php checked($address_type,'work'); ?>> Work</label>
        <label><input type="radio" name="bb_address_type" value="other" <?php checked($address_type,'other'); ?>> Other</label>
    </p>

    <div id="bb_address_error" class="bb-error" style="color:#b91c1c;margin-top:8px;"></div>

    <p>
        <label><strong>Service address</strong><br>
            <input type="text"
                   id="bb_address_input"
                   name="bb_address"
                   value="<?php echo esc_attr($address); ?>"
                   placeholder="Start typing your address..."
                   required
                   style="width:100%;max-width:480px;">
        </label>
    </p>

    <!-- Hidden Google fields -->
    <?php
    echo $wizard->hidden('bb_place_id',      $wizard->posted('bb_place_id'));
    echo $wizard->hidden('bb_address_street',$wizard->posted('bb_address_street'));
    echo $wizard->hidden('bb_address_city',  $wizard->posted('bb_address_city'));
    echo $wizard->hidden('bb_address_state', $wizard->posted('bb_address_state'));
    echo $wizard->hidden('bb_address_zip',   $wizard->posted('bb_address_zip'));
    echo $wizard->hidden('bb_address_lat',   $wizard->posted('bb_address_lat'));
    echo $wizard->hidden('bb_address_lng',   $wizard->posted('bb_address_lng'));
    ?>

    <div id="bb_address_preview" style="font-size:13px;color:#4b5563;margin-top:4px;">
        <?php
        $preview = array_filter([
            $wizard->posted('bb_address_city'),
            $wizard->posted('bb_address_state'),
            $wizard->posted('bb_address_zip')
        ]);
        if (!empty($preview)) {
            echo 'Detected: '.esc_html(implode(', ', $preview));
        }
        ?>
    </div>

    <p>
        <label><strong>Notes</strong><br>
            <input type="text"
                   name="bb_address_extra"
                   value="<?php echo esc_attr($address_extra); ?>"
                   placeholder="gate code, etc."
                   style="width:100%;max-width:480px;">
        </label>
    </p>

    <div class="bb-actions">
        <button type="submit"
                name="bb_back"
                value="1"
                class="bb-btn bb-btn-secondary">
            &laquo; Back
        </button>

        <button type="submit"
                name="bb_continue"
                value="1"
                class="bb-btn bb-btn-primary">
            Continue &raquo;
        </button>
    </div>
</form>
