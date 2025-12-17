<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Wizard – step "Add-ons"
 *
 * Variables disponibles desde wizard-shell.php:
 *  - array $step_data
 *      - $step_data['addons']           => catálogo de add-ons
 *      - $step_data['selected_addons']  => array de IDs seleccionados
 *      - $step_data['errors']           => array de mensajes de error (opcional)
 */

// Aseguramos que $step_data exista
$step_data = isset( $step_data ) && is_array( $step_data ) ? $step_data : array();

$addons          = isset( $step_data['addons'] ) ? (array) $step_data['addons'] : array();
$selected_addons = isset( $step_data['selected_addons'] ) ? (array) $step_data['selected_addons'] : array();
$errors          = isset( $step_data['errors'] ) ? (array) $step_data['errors'] : array();
?>

<div class="bb-step bb-step-addons">
    <p><?php esc_html_e( 'Choose any extra services you would like to add to your booking.', 'bubbles-booking' ); ?></p>

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="bb-errors bb-errors-addons">
            <?php foreach ( $errors as $msg ) : ?>
                <p><?php echo esc_html( $msg ); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( empty( $addons ) ) : ?>

        <p><?php esc_html_e( 'No add-ons are available for this service at the moment.', 'bubbles-booking' ); ?></p>

    <?php else : ?>

        <div class="bb-addons-grid">
            <?php foreach ( $addons as $addon ) :

                $id = isset( $addon['id'] ) ? (int) $addon['id'] : 0;
                if ( $id <= 0 ) {
                    continue;
                }

                $label = isset( $addon['label'] ) ? $addon['label'] : '';
                $desc  = isset( $addon['description'] ) ? $addon['description'] : '';
                $price = isset( $addon['price'] ) ? (float) $addon['price'] : 0.0;

                $is_selected = in_array( $id, $selected_addons, true );
                ?>
                <label class="bb-addon-item">
                    <input
                        type="checkbox"
                        name="bb_addons[]"
                        value="<?php echo esc_attr( $id ); ?>"
                        <?php checked( $is_selected ); ?>
                    />
                    <span class="bb-addon-main">
                        <span class="bb-addon-title">
                            <?php echo esc_html( $label ); ?>
                        </span>

                        <?php if ( $price > 0 ) : ?>
                            <span class="bb-addon-price">
                                <?php
                                /* translators: %s = price */
                                echo esc_html(
                                    sprintf(
                                        __( '$%s', 'bubbles-booking' ),
                                        number_format_i18n( $price, 2 )
                                    )
                                );
                                ?>
                            </span>
                        <?php endif; ?>
                    </span>

                    <?php if ( ! empty( $desc ) ) : ?>
                        <span class="bb-addon-desc">
                            <?php echo esc_html( $desc ); ?>
                        </span>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <!-- Paso actual -->
    <input type="hidden" name="bb_step" value="addons" />

</div>
