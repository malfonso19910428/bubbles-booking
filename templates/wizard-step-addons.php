<?php
if (!defined('ABSPATH')) exit;

/**
 * Template: Step 3 - Add-ons (optional)
 *
 * Variables disponibles:
 * - $wizard        (instancia de Bubbles_Wizard)
 * - $addons_catalog (array de add-ons disponibles)
 * - $prev_addons   (array con los add-ons seleccionados previamente)
 * - $selected_pkg  (id del paquete seleccionado)
 */
?>

<h3 class="bb-section-title">Step 3 · Add-ons (optional)</h3>

<form method="post" class="bb-step-form" novalidate>

    <?php
    // Paso actual
    echo $wizard->hidden('bb_step', 'addons');

    // Reinyectar info anterior
    echo $wizard->hidden_vehicle_fields();
    echo $wizard->hidden('bb_package', $selected_pkg);
    echo $wizard->hidden_address_fields();
    echo $wizard->hidden_date_fields();
    echo $wizard->hidden_contact_fields();
    ?>

    <div class="bb-addons">

        <?php if (!empty($addons_catalog)): ?>

            <?php foreach ($addons_catalog as $ad): 
                $slug   = esc_attr($ad['slug']);
                $name   = esc_html($ad['name']);
                $desc   = esc_html($ad['desc']);
                $priceV = isset($ad['price']) ? (float)$ad['price'] : 0;
                $price  = '$' . number_format_i18n($priceV, 2);

                $checked = in_array($ad['slug'], $prev_addons, true) ? 'checked' : '';
            ?>

                <label class="bb-addon-card">
                    <input type="checkbox" name="addons[]" value="<?php echo $slug; ?>" <?php echo $checked; ?> />
                    <div>
                        <h4><?php echo $name; ?> <span style="font-weight:600">(<?php echo $price; ?>)</span></h4>
                        <?php if (!empty($desc)): ?>
                            <p><?php echo $desc; ?></p>
                        <?php endif; ?>
                    </div>
                </label>

            <?php endforeach; ?>

        <?php else: ?>

            <p>No add-ons are available at this moment.</p>

        <?php endif; ?>

    </div>

    <div class="bb-actions">
        <button type="submit" name="bb_back" value="1" class="button">Back</button>
        <button type="submit" name="bb_continue" value="1" class="button button-primary">Continue</button>
    </div>

</form>
