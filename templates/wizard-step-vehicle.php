<?php
/**
 * Template: Wizard – Step 1: Vehicle
 *
 * Variables que vienen desde Bubbles_Vehicle_Picker::render_form():
 *  - $prev
 *  - $errors
 *  - $message
 *  - $saved_posts
 *  - $has_cpt
 */

if (!defined('ABSPATH')) exit;

// Si no hay errores, empezamos con los campos del vehículo vacíos
if (empty($errors)) {
    $prev = array(
        'year'  => '',
        'make'  => '',
        'model' => '',
        'color' => '',
    );
}
?>

<h3 class="bb-section-title">Step 1 · Vehicle info</h3>

<div class="bb-vehicle-step">
    <div class="bb-wrap">

        <?php
        if (!empty($message)) {
            echo $message;
        }

        if (!empty($errors)) : ?>
            <div class="notice notice-error">
                <p><strong>Please fix the following:</strong></p>
                <ul>
                    <?php foreach ($errors as $e) : ?>
                        <li><?php echo esc_html($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- NUEVO: layout en dos columnas -->
        <div class="bb-vehicle-layout">

            <!-- Columna izquierda: formulario de nuevo vehículo -->
            <div class="bb-vehicle-new">
                <h4 class="bb-subtitle">Enter a new vehicle</h4>

                <form method="post" id="bb-form" class="bb-form" novalidate>
                    <?php echo wp_nonce_field('bb_vehicle_form', 'bb_vehicle_nonce', true, false); ?>

                    <div class="bb-steps">
                        <div class="bb-step">
                            <label for="bb-year" class="bb-label">Year</label>
                            <input type="text"
                                   name="car_year"
                                   id="bb-year"
                                   class="bb-input"
                                   placeholder="Type or pick (e.g., 2020)"
                                   value="<?php echo esc_attr($prev['year'] ?? ''); ?>"
                                   autocomplete="off"
                                   inputmode="numeric"
                                   pattern="[0-9]*">
                            <div id="bb-year-suggest" class="bb-suggest"></div>
                        </div>

                        <div class="bb-step">
                            <label for="bb-make" class="bb-label">Make</label>
                            <input type="text"
                                   name="car_make"
                                   id="bb-make"
                                   class="bb-input"
                                   placeholder="Type or pick (e.g., Toyota or T…)"
                                   value="<?php echo esc_attr($prev['make'] ?? ''); ?>"
                                   autocomplete="off">
                            <div id="bb-make-suggest" class="bb-suggest"></div>
                        </div>

                        <div class="bb-step">
                            <label for="bb-model" class="bb-label">Model</label>
                            <input type="text"
                                   name="car_model"
                                   id="bb-model"
                                   class="bb-input"
                                   placeholder="Type or pick"
                                   value="<?php echo esc_attr($prev['model'] ?? ''); ?>"
                                   autocomplete="off">
                            <div id="bb-model-suggest" class="bb-suggest"></div>
                        </div>

                        <div class="bb-step">
                            <label for="bb-color" class="bb-label">Color</label>
                            <input type="text"
                                   name="car_color"
                                   id="bb-color"
                                   class="bb-input"
                                   placeholder="Type or pick a color"
                                   value="<?php echo esc_attr($prev['color'] ?? ''); ?>"
                                   autocomplete="off">
                            <div id="bb-color-suggest" class="bb-suggest"></div>
                        </div>
                    </div>

                    <div class="bb-actions">
                        <button type="submit" name="bb_vehicle_submit" class="button button-primary">
                            See my price
                        </button>
                        <button type="button" id="bb-help" class="button button-secondary">
                            I can’t find my car
                        </button>
                    </div>

                    <div id="bb-help-msg" class="notice" style="display:none;">
                        <p><strong>Can’t find your car?</strong><br>
                            Try selecting a different year or a similar model to find the one closest to your car.</p>
                        <p>If you need assistance, please call
                            <a href="tel:+18552572623">+1 855 257-2623</a>.
                        </p>
                    </div>

                    <p class="bb-muted">
                        🛈 We store your vehicles for <strong>1 year</strong> to make future bookings easier.
                        Your information is not shared with third parties.
                        <a href="/privacy-policy">Learn more</a>
                    </p>
                </form>
            </div><!-- .bb-vehicle-new -->

            <!-- Columna derecha: vehículos guardados -->
            <?php if (!empty($has_cpt)) : ?>
                <aside class="bb-vehicle-saved" aria-label="Saved vehicles">
                    <h4 class="bb-subtitle">Saved vehicles</h4>

                    <div id="bb-saved" class="bb-saved">
                        <?php if (!empty($saved_posts)) : ?>
                            <ul class="bb-list">
                                <?php foreach ($saved_posts as $p) :
                                    $year  = get_post_meta($p->ID, 'year', true);
                                    $make  = get_post_meta($p->ID, 'make', true);
                                    $model = get_post_meta($p->ID, 'model', true);
                                    $color = get_post_meta($p->ID, 'color', true);
                                    $title = trim("$year $make $model ($color)");
                                    ?>
                                    <li class="bb-list-item" style="margin-bottom:0.75rem;">
                                        <div class="bb-vehicle-header">
                                            <span class="bb-vehicle-title">
                                                <strong><?php echo esc_html($title); ?></strong>
                                            </span>
                                        </div>

                                        <div class="bb-vehicle-actions" style="margin-top:0.25rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                                            <button type="button"
                                                    class="bb-saved-btn bb-use-vehicle"
                                                    data-year="<?php echo esc_attr($year); ?>"
                                                    data-make="<?php echo esc_attr($make); ?>"
                                                    data-model="<?php echo esc_attr($model); ?>"
                                                    data-color="<?php echo esc_attr($color); ?>">
                                                See price
                                            </button>

                                            <form method="post" class="bb-inline-form">
                                                <?php echo wp_nonce_field('bb_vehicle_form', 'bb_vehicle_nonce', true, false); ?>
                                                <input type="hidden"
                                                       name="bb_vehicle_remove_cpt"
                                                       value="<?php echo esc_attr($p->ID); ?>">
                                                <button type="submit" class="bb-saved-btn bb-remove-vehicle">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <div class="notice">
                                <p>No saved vehicles yet.</p>
                            </div>
                        <?php endif; ?>
                    </div><!-- #bb-saved -->
                </aside>
            <?php endif; ?>

        </div><!-- .bb-vehicle-layout -->

    </div><!-- .bb-wrap -->
</div><!-- .bb-vehicle-step -->
