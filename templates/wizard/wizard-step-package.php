<?php
/**
 * Template: Wizard Step - Package & price
 *
 * Variables disponibles:
 * - $wizard           -> instancia de Bubbles_Wizard
 * - $pricing_packages -> array desde bb_custom_price_quote()
 * - $meta_all         -> metadatos de Bubbles_Packages::get_all()
 * - $selected_pkg     -> ID del paquete seleccionado (string)
 */
?>

<h3 class="bb-section-title">Step 2 · Package & price</h3>

<form method="post" class="bb-step-form" novalidate>

    <?php
    // Hidden fields
    echo $wizard->hidden('bb_step','package');
    echo $wizard->hidden_vehicle_fields();
    echo $wizard->hidden_addons_fields();
    echo $wizard->hidden_address_fields();
    echo $wizard->hidden_date_fields();
    echo $wizard->hidden_contact_fields();
    ?>

    <div class="bb-packages">
        <?php foreach ($pricing_packages as $p): ?>
            <?php
            if (empty($p['id'])) continue;

            $id    = $p['id'];
            $price = isset($p['price']) ? (float) $p['price'] : 0;
            $price_label = '$' . number_format_i18n($price, 2);

            $meta = isset($meta_all[$id]) ? $meta_all[$id] : array();

            $name        = $meta['name']           ?? ($p['label'] ?? $id);
            $description = $meta['description']    ?? '';
            $duration    = $meta['duration_hours'] ?? 2;
            $icon        = $meta['icon']           ?? '';
            $class_det   = $p['class_detected']    ?? '';

            $chk = checked($selected_pkg, $id, false);
            ?>
            <label class="bb-card">
                <input type="radio" name="bb_package" value="<?php echo esc_attr($id); ?>" <?php echo $chk; ?> required>

                <div class="bb-card-title">
                    <strong><?php echo esc_html(trim($icon.' '.$name)); ?></strong>
                    — <?php echo esc_html($price_label); ?>
                </div>

                <?php if (!empty($class_det)): ?>
                    <div class="bb-card-class">Class detected: <?php echo esc_html($class_det); ?></div>
                <?php endif; ?>

                <?php if (!empty($description)): ?>
                    <div class="bb-card-desc"><?php echo esc_html($description); ?></div>
                <?php endif; ?>

                <div class="bb-card-duration">
                    Duration: <?php echo esc_html($duration); ?> hours
                </div>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="bb-actions">
        <button type="submit" name="bb_back" value="1" class="button">Back</button>
        <button type="submit" name="bb_continue" value="1" class="button button-primary">Continue</button>
    </div>

</form>
