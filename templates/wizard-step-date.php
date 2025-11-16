<?php
if (!defined('ABSPATH')) exit;

/**
 * Template: Step 5 - Date & time
 *
 * Variables:
 * - $wizard
 * - $errors
 * - $bb_date
 * - $bb_time
 * - $slots
 * - $month_label
 * - $year
 * - $month
 * - $first_weekday
 * - $days_in_month
 * - $is_bookable
 * - $allow_prev
 * - $allow_next
 */
?>

<h3 class="bb-section-title">Step 5 · Date & time</h3>

<?php if (!empty($errors['date'])): ?>
    <p class="bb-error" style="color:#b91c1c;">
        <?php echo esc_html($errors['date']); ?>
    </p>
<?php else: ?>
    <p>Select a date and then show available time slots.</p>
<?php endif; ?>

<form method="post" class="bb-step-form" novalidate>

<?php
echo $wizard->hidden('bb_step','date');
echo $wizard->hidden_vehicle_fields();
echo $wizard->hidden('bb_package', $wizard->posted('bb_package'));
echo $wizard->hidden_addons_fields();
echo $wizard->hidden_address_fields();
echo $wizard->hidden_contact_fields();
?>

<input type="hidden" name="bb_cal_year" value="<?php echo esc_attr($year); ?>">
<input type="hidden" name="bb_cal_month" value="<?php echo esc_attr($month); ?>">

<div class="bb-calendar-wrap">

    <div class="bb-calendar-header">
        <button type="submit" name="bb_cal_prev" <?php echo $allow_prev?'':'disabled'; ?>>«</button>
        <strong><?php echo esc_html($month_label); ?></strong>
        <button type="submit" name="bb_cal_next" <?php echo $allow_next?'':'disabled'; ?>>»</button>
    </div>

    <table class="bb-calendar">
        <thead>
            <tr><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th></tr>
        </thead>
        <tbody>
            <?php
            $day  = 1;
            $cell = 1;

            echo '<tr>';

            while ($cell < $first_weekday) {
                echo '<td class="bb-cal-empty"></td>';
                $cell++;
            }

            while ($day <= $days_in_month) {
                if ($cell > 7) {
                    echo '</tr><tr>';
                    $cell = 1;
                }

                $ymd = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $valid    = $is_bookable($ymd);
                $selected = ($bb_date === $ymd);

                if (!$valid) {
                    echo '<td class="bb-cal-disabled"><span>'.$day.'</span></td>';
                } else {
                    echo '<td class="bb-cal-day">';
                    echo '<label><input type="radio" name="bb_date" value="'.$ymd.'" '.checked($selected,true,false).'><span>'.$day.'</span></label>';
                    echo '</td>';
                }

                $day++;
                $cell++;
            }

            while ($cell <= 7) {
                echo '<td class="bb-cal-empty"></td>';
                $cell++;
            }

            echo '</tr>';
            ?>
        </tbody>
    </table>
</div>

<?php if (!empty($bb_date)): ?>
    <?php if (!empty($slots)): ?>
        <p>
            <label>Available time slots for <strong><?php echo esc_html($bb_date); ?></strong><br>
                <select name="bb_time" required>
                    <option value="">Select a time slot…</option>
                    <?php foreach ($slots as $s): ?>
                        <option value="<?php echo esc_attr($s['value']); ?>" <?php selected($bb_time,$s['value']); ?>>
                            <?php echo esc_html($s['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
    <?php else: ?>
        <p class="bb-error" style="color:#b91c1c;">No slots available for this day.</p>
    <?php endif; ?>
<?php endif; ?>

<div class="bb-actions">
    <button type="submit" name="bb_back" value="1" class="button">Back</button>
    <button type="submit" name="bb_change_day" value="1" class="button">Show time slots</button>
    <?php if (!empty($slots)): ?>
        <button type="submit" name="bb_continue" value="1" class="button button-primary">Continue</button>
    <?php endif; ?>
</div>

</form>
