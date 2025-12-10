<?php 
/**
 * Bubbles Booking – Shell del wizard
 *
 * Estructura:
 *  - Barra de pasos arriba
 *  - Columna izquierda: contenido del paso actual
 *  - Columna derecha: resumen "Your booking"
 *
 * Variables esperadas:
 *  - $wizard       => instancia de Bubbles_Wizard
 *  - $current_step => slug del paso actual (vehicle, package, addons, address, date, confirm)
 *  - $state        => array devuelto por $wizard->get_current_state()
 */

if (!defined('ABSPATH')) exit;

if (!isset($wizard) || !($wizard instanceof Bubbles_Wizard)) {
    echo '<p class="bb-error">Wizard not available.</p>';
    return;
}

// Aseguramos tener steps y state
$steps = method_exists($wizard, 'get_steps')
    ? $wizard->get_steps()
    : array('vehicle','package','addons','address','date','confirm');

if (!isset($state) || !is_array($state)) {
    $state = $wizard->get_current_state();
}
?>

<div class="bb-wizard bb-wizard-wrapper">

    <!-- Barra de pasos arriba -->
    <div class="bb-steps-top">
        <?php foreach ($steps as $index => $step_slug): ?>
            <?php
            $step_number = $index + 1;

            // Etiqueta legible
            switch ($step_slug) {
                case 'vehicle': $label = 'Vehicle';      break;
                case 'package': $label = 'Package';      break;
                case 'addons':  $label = 'Add-ons';      break;
                case 'address': $label = 'Address';      break;
                case 'date':    $label = 'Date & time';  break;
                case 'confirm': $label = 'Confirm';      break;
                default:        $label = ucfirst($step_slug);
            }

            // Estado visual
            $css_step = 'bb-step-item';
            if ($step_slug === $current_step) {
                $css_step .= ' is-active';
            } elseif ($wizard->is_step_completed($step_slug, $state)) {
                $css_step .= ' is-complete';
            }
            ?>
            <div class="<?php echo esc_attr($css_step); ?>">
                <?php echo esc_html($step_number . '. ' . $label); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Layout columnas: izquierda contenido, derecha resumen -->
    <div class="bb-layout bb-left-content-right-summary">

        <!-- Contenido del paso actual -->
        <main class="bb-step-content bb-panel">
            <?php echo $wizard->render_step($current_step); ?>
        </main>

        <!-- Resumen lateral (delegado a Bubbles_Summary + summary-sidebar.php) -->
        <?php
        // Cargar clase resumen
        if (!class_exists('Bubbles_Summary')) {
            require_once BB_PLUGIN_DIR . 'includes/core/class-bubbles-summary.php';
        }

        // Crear instancia del resumen usando el estado actual
        $summary = new Bubbles_Summary($state);

        // Incluir plantilla del resumen
        $summary_tpl = BB_PLUGIN_DIR . 'templates/wizard/summary-sidebar.php';

        if (file_exists($summary_tpl)) {
            include $summary_tpl;
        } else {
            echo '<aside class="bb-summary"><p class="bb-error">Missing summary template.</p></aside>';
        }
        ?>

    </div>
</div>
