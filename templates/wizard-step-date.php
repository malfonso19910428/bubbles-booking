<?php
/**
 * Wizard Step 5: Date & Time
 *
 * Variables que vienen desde Bubbles_Wizard::render_step_date():
 * - $bb_date
 * - $bb_time
 * - $month_label
 * - $year, $month
 * - $days_in_month
 * - $first_weekday
 * - $is_bookable (callable)
 * - $slots
 * - $errors
 * - $allow_prev, $allow_next
 * - $wizard (instancia de Bubbles_Wizard)
 */

if (!defined('ABSPATH')) exit;
?>

<h3 class="bb-section-title">Step 5 · Date &amp; time</h3>

<?php if (!empty($errors['date'])): ?>
    <p class="bb-error">
        <?php echo esc_html($errors['date']); ?>
    </p>
<?php else: ?>
    <p>Please choose a date and time for your service.</p>
<?php endif; ?>

<form method="post" class="bb-step-form bb-step-date" novalidate>

    <?php wp_nonce_field('bb_wizard_date', 'bb_wizard_date_nonce'); ?>

    <!-- El wizard sabe que estamos en el paso "date" -->
    <input type="hidden" name="bb_step" value="date">

    <?php
    // Reinyectar datos de pasos anteriores para no perderlos
    if (isset($wizard) && $wizard instanceof Bubbles_Wizard) {
        echo $wizard->hidden_vehicle_fields();   // car_year, car_make, car_model
        echo $wizard->hidden_addons_fields();    // addons[]
        echo $wizard->hidden_address_fields();   // address + place_id + campos estructurados
    }

    // Mantener el paquete seleccionado
    $selected_pkg = $wizard->posted('bb_package');
    ?>
    <input type="hidden" name="bb_package" value="<?php echo esc_attr($selected_pkg); ?>">

    <?php
    // 👉 CLAVE: mantener la fecha seleccionada entre submits
    if (!empty($bb_date)): ?>
        <input type="hidden" name="bb_date" value="<?php echo esc_attr($bb_date); ?>">
    <?php endif; ?>

    <!-- Layout: calendario a la izquierda, slots a la derecha -->
    <div class="bb-date-layout">

        <!-- Columna 1: Calendario -->
        <div class="bb-calendar-wrap">

            <!-- Navegación del calendario (mes anterior / siguiente) -->
            <div class="bb-calendar-header">

                <button type="submit"
                        name="bb_cal_prev"
                        value="1"
                        class="bb-cal-nav"
                        <?php disabled(!$allow_prev); ?>>
                    &laquo;
                </button>

                <span class="bb-cal-month">
                    <?php echo esc_html($month_label); ?>
                </span>

                <button type="submit"
                        name="bb_cal_next"
                        value="1"
                        class="bb-cal-nav"
                        <?php disabled(!$allow_next); ?>>
                    &raquo;
                </button>

            </div>

            <input type="hidden" name="bb_cal_year"  value="<?php echo esc_attr($year); ?>">
            <input type="hidden" name="bb_cal_month" value="<?php echo esc_attr($month); ?>">

            <!-- Calendario mensual -->
            <table class="bb-calendar">
                <thead>
                    <tr>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                        <th>Sun</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $day  = 1;
                $cell = 1;

                while ($day <= $days_in_month) {
                    echo '<tr>';
                    for ($col = 1; $col <= 7; $col++, $cell++) {

                        // Celdas vacías antes del primer día
                        if ($cell < $first_weekday || $day > $days_in_month) {
                            echo '<td class="bb-cal-empty"></td>';
                            continue;
                        }

                        $ymd         = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $bookable    = $is_bookable($ymd);
                        $is_selected = ($bb_date === $ymd);

                        echo '<td>';

                        if ($bookable) {
                            ?>
                            <button
                                type="submit"
                                name="bb_date"
                                value="<?php echo esc_attr($ymd); ?>"
                                class="bb-cal-day<?php echo $is_selected ? ' is-selected' : ''; ?>"
                            >
                                <?php echo (int) $day; ?>
                            </button>
                            <?php
                        } else {
                            ?>
                            <span class="bb-cal-day disabled">
                                <?php echo (int) $day; ?>
                            </span>
                            <?php
                        }

                        echo '</td>';
                        $day++;
                    }
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>
        </div><!-- /.bb-calendar-wrap -->

        <!-- Columna 2: Time slots (Morning / Afternoon / Evening) -->
        <div class="bb-slots-panel">

            <?php if (!empty($bb_date)): ?>

                <h4 class="bb-time-title">
                    Available time slots for <?php echo esc_html($bb_date); ?>
                </h4>

                <?php
                // Agrupar slots en Morning / Afternoon / Evening
                $groups = array(
                    'Morning'   => array(),
                    'Afternoon' => array(),
                    'Evening'   => array(),
                );

                if (!empty($slots) && is_array($slots)) {
                    foreach ($slots as $slot) {
                        $value = isset($slot['value']) ? $slot['value'] : '';
                        $label = isset($slot['label']) ? $slot['label'] : $value;

                        if (empty($value)) {
                            // Si no hay valor claro, lo mandamos al grupo Afternoon por defecto
                            $groups['Afternoon'][] = array('value' => $value, 'label' => $label);
                            continue;
                        }

                        // Asumimos formato HH:MM-... para obtener la hora inicial
                        $hour = (int) substr($value, 0, 2);

                        if ($hour < 12) {
                            $groups['Morning'][] = array('value' => $value, 'label' => $label);
                        } elseif ($hour < 17) {
                            $groups['Afternoon'][] = array('value' => $value, 'label' => $label);
                        } else {
                            $groups['Evening'][] = array('value' => $value, 'label' => $label);
                        }
                    }
                }
                ?>

                <div class="bb-time-groups">

                    <?php foreach ($groups as $group_label => $items): ?>
                        <div class="bb-time-group-section">

                            <div class="bb-time-group-title">
                                <?php echo esc_html($group_label); ?>
                            </div>

                            <?php if (!empty($items)): ?>

                                <div class="bb-time-group-slots">
                                    <?php foreach ($items as $slot):
                                        $value   = $slot['value'];
                                        $label   = $slot['label'];
                                        $checked = ($bb_time === $value);
                                    ?>
                                        <label class="bb-time-slot">
                                            <input
                                                type="radio"
                                                name="bb_time"
                                                value="<?php echo esc_attr($value); ?>"
                                                <?php checked($checked); ?>
                                                required
                                            >
                                            <span><?php echo esc_html($label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                            <?php else: ?>
                                <p class="bb-no-slots">No slots available for this period.</p>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>

                </div><!-- /.bb-time-groups -->

            <?php else: ?>

                <p class="bb-help">Select a date to see available time slots.</p>

            <?php endif; ?>

        </div><!-- /.bb-slots-panel -->

    </div><!-- /.bb-date-layout  esta parte tengo que verla despues a ver como hago esto generico para todos los pasos--> 

       <div class="bb-actions">
        <button type="submit"
                name="bb_back"
                value="1"
                class="bb-btn bb-btn-secondary">
            &laquo; Back
        </button>

        <button disabled type="submit"
                name="bb_continue"
                value="1"
                class="bb-btn bb-btn-primary"
                id="bb_date_continue_btn">
            Continue &raquo;
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Formulario del paso date
    var form = document.querySelector('.bb-step-form.bb-step-date');
    if (!form) return;

    // Botón "Continue"
    var btnContinue = document.getElementById('bb_date_continue_btn');
    if (!btnContinue) return;

    // Todos los radios de time slot
    function getTimeRadios() {
        return form.querySelectorAll('input[name="bb_time"]');
    }

    function updateButton() {
        var radios = getTimeRadios();
        var hasTime = false;

        radios.forEach(function (r) {
            if (r.checked) {
                hasTime = true;
            }
        });

        btnContinue.disabled = !hasTime;
    }

    // Escuchar cambios en los radios
    getTimeRadios().forEach(function (r) {
        r.addEventListener('change', updateButton);
    });

    // Por si venimos de un back con un slot ya marcado
    updateButton();
});
</script>



</form>
