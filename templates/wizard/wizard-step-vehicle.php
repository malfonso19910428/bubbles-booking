<?php
/**
 * Template: Wizard Step – Vehicle
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Defaults
$prev = ( isset( $prev ) && is_array( $prev ) ) ? $prev : array(
    'year'  => '',
    'make'  => '',
    'model' => '',
    'color' => '',
);

$message     = isset( $message ) ? $message : '';
$bb_vehicles = isset( $bb_vehicles ) ? $bb_vehicles : '';

// ✅ Solo rellenar inputs si hubo POST con errores
$errors      = ( isset( $errors ) && is_array( $errors ) ) ? $errors : array();
$fill_inputs = ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $errors ) );

$val = function( $key ) use ( $fill_inputs, $prev ) {
    if ( ! $fill_inputs ) return '';
    return isset( $prev[ $key ] ) ? (string) $prev[ $key ] : '';
};
?>

<?php if ( ! empty( $message ) ) : ?>
    <div class="bb-notice bb-notice-success">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="bb-vehicle-step">

    <?php if ( ! empty( $bb_vehicles ) ) : ?>
        <input type="hidden" name="bb_vehicles" id="bb-vehicles"
               value="<?php echo esc_attr( $bb_vehicles ); ?>">
    <?php endif; ?>

    <div class="bb-vehicle-grid">

        <div class="bb-vehicle-form-fields">

            <p class="bb-field">
                <label for="bb-year">Year</label>
                <input type="text"
                       id="bb-year"
                       name="car_year"
                       class="bb-input bb-input-year"
                       value="<?php echo esc_attr( $val('year') ); ?>"
                       autocomplete="new-password"
                       required>
                <div id="bb-year-suggest" class="bb-suggest" style="display:none;"></div>
            </p>

            <p class="bb-field">
                <label for="bb-make">Make</label>
                <input type="text"
                       id="bb-make"
                       name="car_make"
                       class="bb-input bb-input-make"
                       value="<?php echo esc_attr( $val('make') ); ?>"
                       autocomplete="new-password"
                       required>
                <div id="bb-make-suggest" class="bb-suggest" style="display:none;"></div>
            </p>

            <p class="bb-field">
                <label for="bb-model">Model</label>
                <input type="text"
                       id="bb-model"
                       name="car_model"
                       class="bb-input bb-input-model"
                       value="<?php echo esc_attr( $val('model') ); ?>"
                       autocomplete="new-password"
                       required>
                <div id="bb-model-suggest" class="bb-suggest" style="display:none;"></div>
            </p>

            <p class="bb-field">
                <label for="bb-color">Color</label>
                <input type="text"
                       id="bb-color"
                       name="car_color"
                       class="bb-input bb-input-color"
                       value="<?php echo esc_attr( $val('color') ); ?>"
                       autocomplete="new-password"
                       required>
                <div id="bb-color-suggest" class="bb-suggest" style="display:none;"></div>
            </p>

            <?php wp_nonce_field( 'bb_vehicle_form', 'bb_vehicle_nonce' ); ?>

            <div class="bb-vehicle-actions">
                <button type="button" id="bb-help" class="bb-link-button">
                    Need help?
                </button>
            </div>

            <div id="bb-help-msg" style="display:none;" class="bb-help-msg">
                <p>If your exact vehicle isn’t listed, don’t worry — simply select the closest similar model.</p>
            </div>

        </div>

    </div>
</div>
