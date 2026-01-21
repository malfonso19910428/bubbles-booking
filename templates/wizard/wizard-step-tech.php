<?php
if ( ! defined('ABSPATH') ) exit;

$data = $view_for_template['step_data'] ?? array();
$techs = $data['techs'] ?? array();
$mode  = $data['mode'] ?? 'auto';
$selected_id = $data['selected_id'] ?? null;

// ✅ Si hay tech seleccionado, no marques "auto"
if ( ! empty($selected_id) ) {
  $mode = 'manual';
}
?>

<div class="bb-step bb-step-tech">
  <h2>Select a Technician</h2>

  <div class="bb-tech-list">

    
    <?php foreach ( $techs as $t ): ?>
      <label style="display:block; padding:14px; border:1px solid rgba(0,0,0,.12); border-radius:14px; margin-bottom:12px; cursor:pointer;">
        <div style="display:flex; gap:12px; align-items:flex-start;">

          <!-- The ONLY selector for this tech -->
          <div style="flex:0 0 auto; padding-top:2px;">
            <input
              type="radio"
              name="bb_tech_choice"
              value="<?php echo esc_attr('tech:' . (int)$t['id']); ?>"
              <?php checked( (int)$selected_id, (int)$t['id'] ); ?>
            >
          </div>

          <div style="flex:0 0 auto;">
            <?php if ( ! empty($t['avatar_url']) ): ?>
              <img
                src="<?php echo esc_url($t['avatar_url']); ?>"
                alt="<?php echo esc_attr($t['display_name']); ?>"
                style="width:56px; height:56px; border-radius:999px; object-fit:cover; border:1px solid rgba(0,0,0,.10);"
              />
            <?php else: ?>
              <div style="width:56px; height:56px; border-radius:999px; border:1px solid rgba(0,0,0,.10); display:flex; align-items:center; justify-content:center;">
                <?php echo esc_html( strtoupper(substr((string)$t['display_name'], 0, 1)) ); ?>
              </div>
            <?php endif; ?>
          </div>

          <div style="flex:1 1 auto;">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
              <div>
                <strong style="font-size:1.05em;"><?php echo esc_html($t['display_name']); ?></strong>

                <?php if ( ! empty($t['languages']) ): ?>
                  <div style="margin-top:6px; opacity:.85;">
                    <span style="opacity:.8;">Languages:</span>
                    <?php echo esc_html( implode(', ', (array)$t['languages']) ); ?>
                  </div>
                <?php endif; ?>
              </div>

              <div style="text-align:right; opacity:.9;">
                <?php if ( $t['distance_mi'] !== null ): ?>
                  <div><?php echo esc_html( number_format((float)$t['distance_mi'], 2 ) ); ?> mi away</div>
                <?php endif; ?>

                <?php if ( ! empty($t['status']) ): ?>
                  <div style="opacity:.7; font-size:.9em;"><?php echo esc_html($t['status']); ?></div>
                <?php endif; ?>
              </div>
            </div>

            <?php if ( ! empty($t['bio']) ): ?>
              <div style="margin-top:8px; opacity:.85;">
                <?php echo esc_html( mb_strimwidth((string)$t['bio'], 0, 180, '…') ); ?>
              </div>
            <?php endif; ?>

            <?php if ( ! empty($t['portfolio']) ): ?>
              <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                <?php
                  $imgs = array_slice((array)$t['portfolio'], 0, 4);
                  foreach ( $imgs as $img ):
                ?>
                  <img
                    src="<?php echo esc_url($img); ?>"
                    alt=""
                    style="width:78px; height:58px; border-radius:10px; object-fit:cover; border:1px solid rgba(0,0,0,.08);"
                  />
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </div>
        </div>
      </label>
    <?php endforeach; ?>

  </div>

  <input type="hidden" name="bb_step" value="tech">
</div>
