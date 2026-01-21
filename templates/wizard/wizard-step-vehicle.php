<?php
/**
 * Template: Wizard Step – Vehicle
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Defaults
$prev = ( isset( $prev ) && is_array( $prev ) ) ? $prev : array(
    'year'          => '',
    'make'          => '',
    'model'         => '',
    'color'         => '',
    'type'          => '',
    'job_target_id' => 0,
);

$message       = isset( $message ) ? $message : '';
$bb_vehicles   = isset( $bb_vehicles ) ? $bb_vehicles : '';
$errors        = ( isset( $errors ) && is_array( $errors ) ) ? $errors : array();
$vehicle_types = ( isset( $vehicle_types ) && is_array( $vehicle_types ) ) ? $vehicle_types : array();

// ✅ Siempre pintar valores del state
$val = function( $key ) use ( $prev ) {
    return isset( $prev[ $key ] ) ? (string) $prev[ $key ] : '';
};

$job_target_id = isset( $prev['job_target_id'] ) ? (int) $prev['job_target_id'] : 0;
$current_slug  = isset( $prev['type'] ) ? sanitize_key( (string) $prev['type'] ) : '';
?>

<?php if ( ! empty( $message ) ) : ?>
    <div class="bb-notice bb-notice-success">
        <?php echo esc_html( $message ); ?>
    </div>
<?php endif; ?>

<?php if ( ! empty( $errors ) ) : ?>
    <div class="bb-notice bb-notice-error">
        <ul class="bb-errors">
            <?php foreach ( $errors as $e ) : ?>
                <li><?php echo esc_html( $e ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="bb-vehicle-step">

    <?php if ( ! empty( $bb_vehicles ) ) : ?>
        <input type="hidden" name="bb_vehicles" id="bb-vehicles"
               value="<?php echo esc_attr( $bb_vehicles ); ?>">
    <?php endif; ?>

    <!-- ✅ Hidden: Job Target ID (resolved via AJAX OR backend rebuild) -->
    <input type="hidden"
           name="job_target_id"
           id="bb-job-target-id"
           value="<?php echo esc_attr( (string) $job_target_id ); ?>">

    <div class="bb-vehicle-grid">
        <div class="bb-vehicle-form-fields">

            <!-- Year -->
            <p class="bb-field">
                <label for="bb-year">Year</label>
                <input type="text"
                       id="bb-year"
                       name="car_year"
                       class="bb-input bb-input-year"
                       value="<?php echo esc_attr( $val('year') ); ?>"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="\d{4}"
                       maxlength="4"
                       placeholder="e.g. 2020"
                       required>
                <div id="bb-year-suggest" class="bb-suggest" style="display:none;"></div>
            </p>

            <!-- Make -->
            <p class="bb-field">
                <label for="bb-make">Make</label>
                <input type="text"
                       id="bb-make"
                       name="car_make"
                       class="bb-input bb-input-make"
                       value="<?php echo esc_attr( $val('make') ); ?>"
                       autocomplete="off"
                       required>
                <div id="bb-make-suggest" class="bb-suggest" style="display:none;"></div>
            </p>

            <!-- Model -->
            <p class="bb-field">
                <label for="bb-model">Model</label>
                <input type="text"
                       id="bb-model"
                       name="car_model"
                       class="bb-input bb-input-model"
                       value="<?php echo esc_attr( $val('model') ); ?>"
                       autocomplete="off"
                       required>
                <div id="bb-model-suggest" class="bb-suggest" style="display:none;"></div>
            </p>

            <!-- Color -->
            <p class="bb-field">
                <label for="bb-color">Color</label>
                <input type="text"
                       id="bb-color"
                       name="car_color"
                       class="bb-input bb-input-color"
                       value="<?php echo esc_attr( $val('color') ); ?>"
                       autocomplete="off"
                       required>
                <div id="bb-color-suggest" class="bb-suggest" style="display:none;"></div>
            </p>

            <!-- ✅ Vehicle Type (desde BD via controller) -->
            <p class="bb-field">
                <label for="bb-vehicle-type">Vehicle type</label>
                <select id="bb-vehicle-type"
                        name="car_type"
                        class="bb-input bb-select">
                    <option value="">Auto-detect</option>

                    <?php foreach ( $vehicle_types as $vt ) :
                        $slug  = sanitize_key( (string) ( $vt['slug'] ?? '' ) );
                        $label = (string) ( $vt['label'] ?? $slug );
                        if ( $slug === '' ) continue;
                    ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"
                            <?php selected( $current_slug, $slug ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
                <small class="bb-help-text">
                    Types are loaded from your database.
                </small>
            </p>

            <!-- Optional label: JS can fill -->
            <div id="bb-job-target-label" class="bb-help-text" style="margin-top:-8px; margin-bottom:10px;"></div>

            <?php wp_nonce_field( 'bb_vehicle_form', 'bb_vehicle_nonce' ); ?>

            <div class="bb-vehicle-actions">
                <button type="button" id="bb-help" class="bb-link-button">
                    Need help?
                </button>
            </div>

            <div id="bb-help-msg" style="display:none;" class="bb-help-msg">
                <p>If your exact vehicle isn’t listed, don’t worry — select the closest option.</p>
            </div>

        </div>
    </div>
</div>
