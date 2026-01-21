<?php
if ( ! defined('ABSPATH') ) exit;

$bb_date = (string) ($step_data['bb_date'] ?? '');
$bb_time = (string) ($step_data['bb_time'] ?? '');

$available_dates = $step_data['available_dates'] ?? [];
$min_date = $available_dates ? reset($available_dates) : '';
$max_date = $available_dates ? end($available_dates) : '';

$has_date = ! empty($bb_date);
?>

<div class="bb-date-time-grid">

  <!-- LEFT: Calendar -->
  <div class="bb-date-col">
    <div class="bb-field">
      <label for="bb_date">Choose a date</label>

      <input
        id="bb_date"
        name="bb_date"
        type="text"
        class="bb-date-picker"
        value="<?php echo esc_attr($bb_date); ?>"
        data-min="<?php echo esc_attr($min_date); ?>"
        data-max="<?php echo esc_attr($max_date); ?>"
        hidden
        required
      />
    </div>
  </div>

  <!-- RIGHT: Slots -->
  <div class="bb-time-col">
    <div class="bb-field">
      <label>Choose a time slot</label>

      <div id="bb-slots-panel" class="bb-slots-panel" data-has-date="<?php echo $has_date ? '1' : '0'; ?>">
        <div class="bb-slot-group" data-bucket="Morning">
          <div class="bb-slot-group-title">Morning</div>
          <div class="bb-slot-group-body" data-empty="1">
            <div class="bb-slot-placeholder">Select a date to see morning times</div>
          </div>
        </div>

        <div class="bb-slot-group" data-bucket="Afternoon">
          <div class="bb-slot-group-title">Afternoon</div>
          <div class="bb-slot-group-body" data-empty="1">
            <div class="bb-slot-placeholder">Select a date to see afternoon times</div>
          </div>
        </div>

        <div class="bb-slot-group" data-bucket="Evening">
          <div class="bb-slot-group-title">Evening</div>
          <div class="bb-slot-group-body" data-empty="1">
            <div class="bb-slot-placeholder">Select a date to see evening times</div>
          </div>
        </div>

        <div class="bb-slot-hint" id="bb-slot-hint">
          <?php echo $has_date ? 'Pick a time on the right.' : 'Pick a date on the left to load available times.'; ?>
        </div>

        <div class="bb-slot-error" id="bb-slot-error" style="display:none;"></div>
      </div>

      <!-- ✅ campo real para submit (PRO) -->
      <input type="hidden" id="bb_time" name="bb_time" value="<?php echo esc_attr($bb_time); ?>" />
      <input type="hidden" id="bb_time_label" name="bb_time_label" value="" />
    </div>
  </div>

</div>
