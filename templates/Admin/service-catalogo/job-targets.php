<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Job Targets
 *
 * Expected $view:
 *  - notice (string)
 *  - industry (string)
 *  - items (array)
 *  - edit_item (array|null)
 */

$notice   = isset($view['notice']) ? (string) $view['notice'] : '';
$industry = isset($view['industry']) ? (string) $view['industry'] : '';
$items    = isset($view['items']) && is_array($view['items']) ? $view['items'] : array();
$edit     = (isset($view['edit_item']) && is_array($view['edit_item'])) ? $view['edit_item'] : null;

$industries = array(
    ''     => 'All industries',
    'auto' => 'Auto',
);

$base_url = remove_query_arg( array('edit_id', 'industry', 'bb_action', 'id', '_wpnonce') );

function bbjt_attr($v): string { return esc_attr( (string)$v ); }

// ===== Load pricing tiers (repo -> fallback $wpdb) =====
$tiers = array();

require_once BB_PLUGIN_DIR . 'includes/domain/admin/pricing/tiers/bb-pricing-tiers-repo.php';

if ( class_exists('BB_Pricing_Tiers_Repo') ) {
    $tiers_repo = new BB_Pricing_Tiers_Repo();
    if ( method_exists($tiers_repo, 'get_active_all') ) {
        $tiers = (array) $tiers_repo->get_active_all();
    } elseif ( method_exists($tiers_repo, 'get_all') ) {
        $tiers = (array) $tiers_repo->get_all();
    }
}

if ( empty($tiers) ) {
    global $wpdb;
    $tiers_table = $wpdb->prefix . 'bb_pricing_tiers';
    $tiers = (array) $wpdb->get_results(
        "SELECT id, slug, label, default_add, is_active, sort_order
         FROM {$tiers_table}
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC",
        ARRAY_A
    );
}

?>
<div style="max-width:1100px;">

    <h2 style="margin-top:10px;">Job Targets</h2>

    <p class="description" style="max-width:980px;">
        <strong>Job Target</strong> = what the job is performed on.
        Each target maps to a <strong>Pricing Tier</strong> from <code><?php echo esc_html($wpdb->prefix); ?>bb_pricing_tiers</code>.
    </p>

    <?php if ( $notice !== '' ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php if ( empty($tiers) ) : ?>
        <div class="notice notice-warning">
            <p><strong>No pricing tiers found.</strong> Your tiers table is empty or none are active.</p>
        </div>
    <?php endif; ?>

    <hr />

    <!-- FILTER -->
    <form method="get" action="" style="margin-bottom:12px;">
        <?php foreach ($_GET as $k => $v) {
            if ($k === 'industry') continue;
            echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'"/>';
        } ?>
        <label style="margin-right:8px;"><strong>Industry</strong></label>
        <select name="industry">
            <?php foreach ($industries as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($industry, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button" type="submit">Filter</button>
        <?php if ($industry !== '') : ?>
            <a class="button" href="<?php echo esc_url($base_url); ?>" style="margin-left:6px;">Clear</a>
        <?php endif; ?>
    </form>

    <!-- ADD/EDIT -->
    <h3 style="margin-top:10px;"><?php echo $edit ? 'Edit Job Target' : 'Add Job Target'; ?></h3>

    <form method="post" action="">
        <?php wp_nonce_field('bb_job_targets_save', 'bb_job_targets_nonce'); ?>
        <input type="hidden" name="bb_action" value="save" />
        <input type="hidden" name="id" value="<?php echo $edit ? (int)$edit['id'] : 0; ?>" />

        <table class="form-table" style="max-width:980px;">
            <tr>
                <th scope="row"><label>Industry</label></th>
                <td>
                    <select name="industry" required>
                        <?php foreach ($industries as $key => $label) :
                            if ($key === '') continue;
                            $selected = $edit ? (string)($edit['industry'] ?? 'auto') : ($industry !== '' ? $industry : 'auto');
                        ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($selected, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Controls where this target appears.</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label>Label</label></th>
                <td>
                    <input type="text" name="label" class="regular-text" required
                           value="<?php echo bbjt_attr($edit['label'] ?? ''); ?>"
                           placeholder="Sedan / SUV / Apartment..." />
                    <p class="description">
                        The system will generate the slug automatically from this label.
                        <?php if ( $edit && ! empty($edit['slug']) ) : ?>
                            Current slug: <code><?php echo esc_html((string)$edit['slug']); ?></code>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label>Pricing Tier</label></th>
                <td>
                    <?php $cur_tier_id = $edit ? (int)($edit['tier_id'] ?? 0) : 0; ?>
                    <select name="tier_id" required>
                        <option value="0" disabled <?php selected($cur_tier_id, 0); ?>>— Select tier —</option>
                        <?php foreach ((array)$tiers as $t) :
                            $tid = (int)($t['id'] ?? 0);
                            if ($tid <= 0) continue;

                            $tlabel = (string)($t['label'] ?? ('Tier #' . $tid));
                            $tadd   = isset($t['default_add']) ? (float)$t['default_add'] : 0.0;
                        ?>
                            <option value="<?php echo (int)$tid; ?>" <?php selected($cur_tier_id, $tid); ?>>
                                <?php echo esc_html($tlabel . ' (+$' . number_format($tadd, 2) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">References <code><?php echo esc_html($wpdb->prefix); ?>bb_pricing_tiers.id</code>.</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label>Description</label></th>
                <td>
                    <textarea name="description" class="large-text" rows="3"
                              placeholder="Optional helper text"><?php echo esc_textarea($edit['description'] ?? ''); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row"><label>Active</label></th>
                <td>
                    <?php $is_active = (int)($edit['is_active'] ?? 1); ?>
                    <label><input type="checkbox" name="is_active" value="1" <?php checked($is_active, 1); ?> /> Enabled</label>
                </td>
            </tr>
        </table>

        <p>
            <button class="button button-primary" type="submit" <?php disabled(empty($tiers)); ?>>
                <?php echo $edit ? 'Update' : 'Create'; ?>
            </button>
            <?php if ($edit) : ?>
                <a class="button" href="<?php echo esc_url($base_url); ?>" style="margin-left:6px;">Cancel</a>
            <?php endif; ?>
        </p>
    </form>

    <hr />

    <!-- Seed -->
    <form method="post" action="" style="margin-bottom:10px;">
        <?php wp_nonce_field('bb_job_targets_save', 'bb_job_targets_nonce'); ?>
        <input type="hidden" name="bb_action" value="seed" />
        <button class="button" type="submit">Seed defaults (only if empty)</button>
    </form>

    <!-- LIST (bulk update) -->
    <h3 style="margin-top:10px;">Job Targets List</h3>

    <form method="post" action="">
        <?php wp_nonce_field('bb_job_targets_save', 'bb_job_targets_nonce'); ?>
        <input type="hidden" name="bb_action" value="bulk_update" />

        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th style="width:110px;">Industry</th>
                    <th style="width:170px;">Slug</th>
                    <th>Label</th>
                    <th style="width:240px;">Pricing Tier</th>
                    <th style="width:110px;">Active</th>
                    <th style="width:110px;">Sort</th>
                    <th style="width:220px;">Actions</th>
                </tr>
            </thead>
            <tbody>

            <?php if ( empty($items) ) : ?>
                <tr><td colspan="8"><em>No job targets found.</em></td></tr>
            <?php else : ?>
                <?php foreach ($items as $row) :
                    $id      = (int)($row['id'] ?? 0);
                    $ind     = (string)($row['industry'] ?? '');
                    $slug    = (string)($row['slug'] ?? '');
                    $lab     = (string)($row['label'] ?? '');
                    $tier_id = (int)($row['tier_id'] ?? 0);
                    $act     = (int)($row['is_active'] ?? 0);
                    $sort    = (int)($row['sort_order'] ?? 0);

                    $edit_url = add_query_arg( array('edit_id' => $id), $base_url );

                    // ✅ Delete link with nonce (no nested form)
                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            array(
                                'bb_action' => 'delete',
                                'id'        => $id,
                            ),
                            $base_url
                        ),
                        'bb_job_targets_delete_' . $id
                    );
                ?>
                    <tr>
                        <td><?php echo (int)$id; ?></td>
                        <td><?php echo esc_html($ind); ?></td>
                        <td><code><?php echo esc_html($slug); ?></code></td>
                        <td><strong><?php echo esc_html($lab); ?></strong></td>

                        <td>
                            <select name="rows[<?php echo (int)$id; ?>][tier_id]">
                                <option value="0" disabled>— Select tier —</option>
                                <?php foreach ((array)$tiers as $t) :
                                    $tid = (int)($t['id'] ?? 0);
                                    if ($tid <= 0) continue;

                                    $tlabel = (string)($t['label'] ?? ('Tier #' . $tid));
                                    $tadd   = isset($t['default_add']) ? (float)$t['default_add'] : 0.0;
                                ?>
                                    <option value="<?php echo (int)$tid; ?>" <?php selected($tier_id, $tid); ?>>
                                        <?php echo esc_html($tlabel . ' (+$' . number_format($tadd, 2) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <label>
                                <input type="checkbox" name="rows[<?php echo (int)$id; ?>][is_active]" value="1" <?php checked($act, 1); ?> />
                                Yes
                            </label>
                        </td>

                        <td>
                            <input type="number" name="rows[<?php echo (int)$id; ?>][sort_order]"
                                   value="<?php echo esc_attr($sort); ?>" style="width:90px;" />
                        </td>

                        <td>
                            <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Edit</a>
                            <a class="button button-small button-link-delete"
                               href="<?php echo esc_url($delete_url); ?>"
                               onclick="return confirm('Delete this job target?');">
                               Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            </tbody>
        </table>

        <?php if ( ! empty($items) ) : ?>
            <p style="margin-top:12px;">
                <button class="button button-primary" type="submit" <?php disabled(empty($tiers)); ?>>Save changes</button>
            </p>
        <?php endif; ?>

    </form>
</div>
