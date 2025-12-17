<?php if ( ! defined('ABSPATH') ) exit;

$bb_date = $step_data['bb_date'] ?? '';
$bb_time = $step_data['bb_time'] ?? '';

/**
 * Slots “temporales” (luego lo conectas a availability real)
 * Puedes cambiar rangos/intervalos cuando quieras.
 */
$slots = array(
  '08:00' => '8:00 AM',
  '09:00' => '9:00 AM',
  '10:00' => '10:00 AM',
  '11:00' => '11:00 AM',
  '12:00' => '12:00 PM',
  '13:00' => '1:00 PM',
  '14:00' => '2:00 PM',
  '15:00' => '3:00 PM',
  '16:00' => '4:00 PM',
);
?>

<input type="hidden" name="bb_step" value="date" />


<div class="bb-field">
  <label for="bb_date">Choose a date</label>
  <input
    id="bb_date"
    name="bb_date"
    type="date"
    value="<?php echo esc_attr( $bb_date ); ?>"
    required
  />
</div>

<div class="bb-field">
  <label for="bb_time">Choose a time slot</label>
  <select id="bb_time" name="bb_time" required>
    <option value="">Select a time</option>
    <?php foreach ( $slots as $value => $label ) : ?>
      <option value="<?php echo esc_attr($value); ?>" <?php selected( $bb_time, $value ); ?>>
        <?php echo esc_html($label); ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
