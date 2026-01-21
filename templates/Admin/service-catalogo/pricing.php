<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tab: Pricing (clean)
 *
 * Purpose:
 *  - Manage Job Scope adjustments (XS/S/M/L/XL) used by pricing engine
 *
 * Expected:
 *  - array $bb_pricing_view
 *    - notes (array) optional
 *    - tiers (array): id, slug, label, default_add, sort_order, is_active
 *    - tiers_saved (bool) optional
 *    - tiers_active (array) optional
 */

$notes = isset($bb_pricing_view['notes']) && is_array($bb_pricing_view['notes'])
    ? $bb_pricing_view['notes']
    : array();

$tiers = isset($bb_pricing_view['tiers']) && is_array($bb_pricing_view['tiers'])
    ? $bb_pricing_view['tiers']
    : array();

// ✅ show success notice
$tiers_saved = ! empty($bb_pricing_view['tiers_saved']);

$tiers_active = isset($bb_pricing_view['tiers_active']) && is_array($bb_pricing_view['tiers_active'])
    ? $bb_pricing_view['tiers_active']
    : array();
?>

<div style="max-width: 980px;">

    <h1 style="margin-top: 10px;">Pricing</h1>

    <p class="description">
        Configure pricing rules that work across industries.
        In this screen we only manage <strong>Job Scope adjustments</strong> (Price Add).
        <br>
        <em>Job Target mapping</em> is managed in <strong>Job Targets</strong>, and <em>price previews</em> are shown in <strong>Jobs</strong>.
    </p>

    <?php if ( ! empty($notes) ) : ?>
        <div class="notice notice-info">
            <p><strong>How this works</strong></p>
            <ul style="margin-left:18px;">
                <?php foreach ($notes as $n) : ?>
                    <li><?php echo esc_html($n); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ( $tiers_saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Changes saved.</p>
        </div>
    <?php endif; ?>

    <hr />

    <!-- =========================
         SECTION: JOB SCOPE (XS–XL)
    ========================== -->
    <h2>Job Scope</h2>

    <p class="description" style="max-width: 920px;">
        <strong>Job Scope</strong> defines how big the job is (size / workload), regardless of the industry.
        Set a default <strong>Price Add (+$)</strong> for each scope level.
    </p>

    <div class="notice notice-info" style="max-width: 920px;">
        <p><strong>Job Scope examples by industry</strong></p>
        <ul style="margin-left:18px;">
            <li><strong>Small Job</strong> → Sedan / 1 Bedroom / Basic Lawn</li>
            <li><strong>Medium Job</strong> → SUV / 2 Bedrooms / Medium Lawn</li>
            <li><strong>Large Job</strong> → Van / 3+ Bedrooms / Large Lawn</li>
            <li><strong>XL Job</strong> → Oversize Vehicle / Commercial / Extra Large Area</li>
        </ul>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('bb_pricing_save_tiers', 'bb_pricing_nonce'); ?>
        <input type="hidden" name="bb_pricing_action" value="save_tiers" />

        <table class="widefat striped" style="margin-top: 12px;">
            <thead>
                <tr>
                    <th>Scope Label</th>
                    <th style="width:170px;">Price Add (+$)</th>
                    <th style="width:120px;">Enabled</th>
                    <th style="width:140px;">Disable</th>
                </tr>
            </thead>
            <tbody>

            <?php if ( empty($tiers) ) : ?>
                <tr>
                    <td colspan="4">
                        <em>No scopes found. Seed defaults and reload.</em>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ( $tiers as $i => $t ) :
                    $id    = (int)($t['id'] ?? 0);
                    $slug  = (string)($t['slug'] ?? '');
                    $label = (string)($t['label'] ?? '');
                    $add   = number_format((float)($t['default_add'] ?? 0), 2, '.', '');
                    $sort  = (int)($t['sort_order'] ?? 10);
                    $act   = (int)($t['is_active'] ?? 1);
                ?>
                    <tr>
                        <td>
                            <!-- hidden identity fields for update -->
                            <input type="hidden" name="tiers[<?php echo esc_attr($i); ?>][id]" value="<?php echo esc_attr($id); ?>">
                            <input type="hidden" name="tiers[<?php echo esc_attr($i); ?>][slug]" value="<?php echo esc_attr($slug); ?>">
                            <input type="hidden" name="tiers[<?php echo esc_attr($i); ?>][sort_order]" value="<?php echo esc_attr($sort); ?>">

                            <input type="text"
                                   class="regular-text"
                                   name="tiers[<?php echo esc_attr($i); ?>][label]"
                                   value="<?php echo esc_attr($label); ?>"
                                   placeholder="Small Job"
                                   aria-label="Job scope label" />
                        </td>

                        <td>
                            <input type="number"
                                   step="0.01" min="0"
                                   name="tiers[<?php echo esc_attr($i); ?>][default_add]"
                                   value="<?php echo esc_attr($add); ?>"
                                   style="width: 120px;"
                                   aria-label="Job scope price add" />
                            <p class="description" style="margin:6px 0 0;">
                                Added to Base Price when this scope applies.
                            </p>
                        </td>

                        <td>
                            <label>
                                <input type="checkbox"
                                       name="tiers[<?php echo esc_attr($i); ?>][is_active]"
                                       value="1" <?php checked($act, 1); ?> />
                                Yes
                            </label>
                        </td>

                        <td>
                            <label title="Safe remove: it disables the scope without deleting data.">
                                <input type="checkbox"
                                       name="tiers[<?php echo esc_attr($i); ?>][_delete]"
                                       value="1" />
                                Disable
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            </tbody>
        </table>

        <p style="margin-top: 12px;">
            <button type="submit" class="button button-primary">Save changes</button>
        </p>

        <p class="description" style="margin-top: 8px;">
            <strong>Note:</strong> “Disable” is a safe remove. It keeps history and prevents breaking pricing links later.
        </p>
    </form>

</div>
