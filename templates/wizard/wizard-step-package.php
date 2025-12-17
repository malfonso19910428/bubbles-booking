<?php
/**
 * Template: Wizard Step - Package & price
 *
 * Esta plantilla se usa dentro de wizard-shell.php
 *
 * Recibe:
 *   - $step (array) con:
 *       - pricing_packages
 *       - meta_all
 *       - selected_pkg
 *
 * La shell ya abre el <form> y pinta los botones Back / Continue.
 * Aquí SOLO dibujamos los campos del paso.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Tomamos los datos desde $step (vienen de BB_Services_Controller::get_view_data)
$pricing_packages = isset( $step['pricing_packages'] ) ? (array) $step['pricing_packages'] : array();
$meta_all         = isset( $step['meta_all'] )         ? (array) $step['meta_all']         : array();
$selected_pkg     = isset( $step['selected_pkg'] )     ? (string) $step['selected_pkg']    : '';


?>



<?php if ( empty( $pricing_packages ) ) : ?>

    <p>No packages available at this moment.</p>

<?php else : ?>

    <div class="bb-packages">
        <?php foreach ( $pricing_packages as $p ) : ?>
            <?php
            if ( empty( $p['id'] ) ) {
                continue;
            }

            $id    = (string) $p['id'];
            $price = isset( $p['price'] ) ? (float) $p['price'] : 0;
            $price_label = '$' . number_format_i18n( $price, 2 );

            $meta = isset( $meta_all[ $id ] ) ? $meta_all[ $id ] : array();

            $name        = $meta['name']           ?? ( $p['label'] ?? $id );
            $description = $meta['description']    ?? '';
            $duration    = $meta['duration_hours'] ?? 2;
            $icon        = $meta['icon']           ?? '';
            $class_det   = $p['class_detected']    ?? '';

            $chk = checked( $selected_pkg, $id, false );
            ?>
            <label class="bb-card">
                <input
                    type="radio"
                    name="bb_package"
                    value="<?php echo esc_attr( $id ); ?>"
                    <?php echo $chk; ?>
                    required
                />

                <div class="bb-card-title">
                    <strong><?php echo esc_html( trim( $icon . ' ' . $name ) ); ?></strong>
                    — <?php echo esc_html( $price_label ); ?>
                </div>

                <?php if ( ! empty( $class_det ) ) : ?>
                    <div class="bb-card-class">
                        Class detected: <?php echo esc_html( $class_det ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $description ) ) : ?>
                    <div class="bb-card-desc">
                        <?php echo esc_html( $description ); ?>
                    </div>
                <?php endif; ?>

                <div class="bb-card-duration">
                    Duration: <?php echo esc_html( $duration ); ?> hours
                </div>
            </label>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
