<?php
/**
 * Template: Wizard Step - Package & price
 *
 * Recibe (desde BB_Services_Controller::get_view_data):
 *   $step['pricing_packages']  = [ ['id','price','label','class_detected'?...], ... ]
 *   $step['meta_all']          = [ id => ['name','description','duration_hours','icon'], ... ]
 *   $step['selected_pkg']      = '3' (string)
 *
 * Opcional (si luego lo pasas):
 *   $step['vehicle_type']      = 'sedan' | 'suv' | ...
 *   $step['vehicle_label']     = '2020 Toyota Camry (Blue)'
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$pricing_packages = isset( $step['pricing_packages'] ) ? (array) $step['pricing_packages'] : array();
$meta_all         = isset( $step['meta_all'] ) ? (array) $step['meta_all'] : array();
$selected_pkg     = isset( $step['selected_pkg'] ) ? (string) $step['selected_pkg'] : '';

// Opcional (no rompe si no existe)
$vehicle_type  = isset($step['vehicle_type']) ? (string) $step['vehicle_type'] : '';
$vehicle_label = isset($step['vehicle_label']) ? (string) $step['vehicle_label'] : '';
?>

<?php if ( $vehicle_label !== '' || $vehicle_type !== '' ) : ?>
  <div class="bb-note" style="margin:0 0 12px;">
    <?php if ( $vehicle_label !== '' ) : ?>
      <div><strong>Vehicle:</strong> <?php echo esc_html($vehicle_label); ?></div>
    <?php endif; ?>
    <?php if ( $vehicle_type !== '' ) : ?>
      <div><strong>Type:</strong> <?php echo esc_html( ucfirst($vehicle_type) ); ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ( empty( $pricing_packages ) ) : ?>

  <p>No packages available at this moment.</p>

<?php else : ?>

  <div class="bb-packages" role="radiogroup" aria-label="Packages">
    <?php foreach ( $pricing_packages as $p ) : ?>
      <?php
      if ( empty( $p['id'] ) ) continue;

      $id    = (string) $p['id'];
      $price = isset( $p['price'] ) ? (float) $p['price'] : 0.0;

      // Display
      $price_label = '$' . number_format_i18n( $price, 2 );

      $meta = isset( $meta_all[ $id ] ) && is_array( $meta_all[ $id ] )
        ? $meta_all[ $id ]
        : array();

      $name        = isset($meta['name']) ? (string)$meta['name'] : ( isset($p['label']) ? (string)$p['label'] : $id );
      $description = isset($meta['description']) ? (string)$meta['description'] : '';
      $duration    = isset($meta['duration_hours']) ? (float)$meta['duration_hours'] : 2.0;
      $icon        = isset($meta['icon']) ? (string)$meta['icon'] : '';
      $class_det   = isset($p['class_detected']) ? (string)$p['class_detected'] : '';

      $is_checked = checked( $selected_pkg, $id, false );
      ?>

      <label class="bb-card" data-bb-package-id="<?php echo esc_attr($id); ?>">
        <input
          type="radio"
          name="bb_package"
          value="<?php echo esc_attr( $id ); ?>"
          <?php echo $is_checked; ?>
          required
        />

        <div class="bb-card-title">
          <strong><?php echo esc_html( trim( $icon . ' ' . $name ) ); ?></strong>
          — <?php echo esc_html( $price_label ); ?>
        </div>

        <?php if ( $class_det !== '' ) : ?>
          <div class="bb-card-class">
            Suggested class: <?php echo esc_html( $class_det ); ?>
          </div>
        <?php endif; ?>

        <?php if ( $description !== '' ) : ?>
          <div class="bb-card-desc">
            <?php echo esc_html( $description ); ?>
          </div>
        <?php endif; ?>

        <div class="bb-card-duration">
          Duration: <?php echo esc_html( rtrim(rtrim(number_format((float)$duration, 2, '.', ''), '0'), '.') ); ?> hours
        </div>
      </label>

    <?php endforeach; ?>
  </div>

  <div class="bb-note" style="margin-top:12px;">
    Prices shown are based on your vehicle selection. Add-ons are calculated later.
  </div>

<?php endif; ?>
