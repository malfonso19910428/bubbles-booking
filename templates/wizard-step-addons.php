<?php
if (!defined('ABSPATH')) exit;

/**
 * Template: Wizard Step - Add-ons
 *
 * Variables esperadas:
 * - $wizard         -> instancia de Bubbles_Wizard
 * - $addons_catalog -> catálogo de add-ons (array)
 * - $prev_addons    -> array de slugs seleccionados previamente
 * - $selected_pkg   -> ID del paquete elegido en el paso anterior
 */

$addons      = isset($addons_catalog) && is_array($addons_catalog) ? $addons_catalog : array();
$prev_addons = isset($prev_addons) && is_array($prev_addons) ? $prev_addons : array();
$selected_pkg = isset($selected_pkg) ? $selected_pkg : '';
?>

<h3 class="bb-section-title">Step 3 · Add-ons</h3>

<form method="post" class="bb-step-form" novalidate>

    <?php
    // Paso actual
    echo $wizard->hidden('bb_step', 'addons');

    // Mantener datos de pasos anteriores
    echo $wizard->hidden_vehicle_fields();
    echo $wizard->hidden_addons_fields();   // por si vienes de atrás con algo seleccionado
    echo $wizard->hidden_address_fields();
    echo $wizard->hidden_date_fields();
    echo $wizard->hidden_contact_fields();

    // Mantener el paquete seleccionado
    if (!empty($selected_pkg)) {
        echo $wizard->hidden('bb_package', $selected_pkg);
    }
    ?>

    <div class="bb-addons-grid">
        <?php foreach ($addons as $addon):

            $slug   = $addon['slug']  ?? '';
            if ($slug === '') continue;

            $name   = $addon['name']  ?? $slug;
            $desc   = $addon['desc']  ?? '';
            $price  = $addon['price'] ?? '';
            $badge  = $addon['badge'] ?? '';

            $checked = in_array($slug, $prev_addons, true);
        ?>
            <label class="bb-addon-card<?php echo $checked ? ' is-selected' : ''; ?>">
                <input type="checkbox"
                       class="bb-addon-checkbox"
                       name="addons[]"
                       value="<?php echo esc_attr($slug); ?>"
                       <?php checked($checked); ?>>

                <span class="bb-addon-title">
                    <?php echo esc_html($name); ?>
                    <?php if ($badge): ?>
                        <span class="bb-addon-badge"><?php echo esc_html($badge); ?></span>
                    <?php endif; ?>
                </span>

                <?php if ($price !== ''): ?>
                    <span class="bb-addon-price">
                        + $<?php echo esc_html(number_format((float) $price, 0)); ?>
                    </span>
                <?php endif; ?>

                <?php if ($desc): ?>
                    <span class="bb-addon-desc">
                        <?php echo esc_html($desc); ?>
                    </span>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="bb-actions">
        <button type="submit" name="bb_back" value="1" class="bb-btn bb-btn-secondary">
            &laquo; Back
        </button>
        
      <!-- BOTÓN ADD ANOTHER VEHICLE (ENVÍA EL FORMULARIO) -->
    <button type="submit"
            name="bb_add_vehicle"
            value="1"
            class="bb-btn bb-btn-tertiary">
        + Add another vehicle
    </button>
        <button type="submit" name="bb_continue" value="1" class="bb-btn bb-btn-primary">
            Continue
        </button>
    </div>

</form>
