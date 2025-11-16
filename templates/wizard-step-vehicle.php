<?php
if (!defined('ABSPATH')) exit;

/**
 * Template: Step 1 - Vehicle info
 *
 * Variables disponibles:
 * - $wizard (instancia de Bubbles_Wizard, por si la necesitas luego)
 */
?>
<h3 class="bb-section-title">Step 1 · Vehicle info</h3>

<div class="bb-vehicle-step">
    <?php
    /**
     * Aquí simplemente renderizamos tu Vehicle Picker.
     *
     * IMPORTANTE:
     * - El shortcode [bubbles_vehicle_picker] debe:
     *      - Mostrar el selector de vehículo
     *      - Enviar el formulario con un campo oculto bb_vehicle_submit (como ya hacía antes)
     * - El wizard detecta ese submit en bubbles-wizard.php:
     *      if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['bb_vehicle_submit']) ) {
     *          $current_step = 'package';
     *      }
     */
    echo do_shortcode('[bubbles_vehicle_picker]');
    ?>
</div>

<script>
(function(){
    // Si tienes una función global JS para re-inicializar el picker, la llamamos aquí:
    var panel = document.currentScript && document.currentScript.closest('.bb-panel');
    if (window.BB_PICKER_INIT) {
        window.BB_PICKER_INIT(panel || document);
    }
})();
</script>
