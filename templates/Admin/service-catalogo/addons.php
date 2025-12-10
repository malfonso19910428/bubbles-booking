<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array $bb_addons_list_local */
$addons = is_array( $bb_addons_list_local ) ? $bb_addons_list_local : array();
?>

<h2>Add new add-on</h2>

<form method="post">
    <?php wp_nonce_field( 'bb_addons_add' ); ?>
    <input type="hidden" name="bb_addons_action" value="add">

    <table class="form-table" role="presentation">
        <tbody>
        <tr>
            <th scope="row"><label for="addon_name">Name</label></th>
            <td><input type="text" name="addon_name" id="addon_name" class="regular-text" required></td>
        </tr>
        <tr>
            <th scope="row"><label for="addon_description">Description</label></th>
            <td><textarea name="addon_description" id="addon_description" class="large-text" rows="3"></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="addon_price">Price</label></th>
            <td><input type="number" step="0.01" min="0" name="addon_price" id="addon_price" class="small-text"></td>
        </tr>
        <tr>
            <th scope="row"><label for="addon_duration">Duration (min)</label></th>
            <td><input type="number" step="1" min="0" name="addon_duration" id="addon_duration" class="small-text"></td>
        </tr>
        <tr>
            <th scope="row">Active</th>
            <td>
                <label>
                    <input type="checkbox" name="addon_active" value="1" checked>
                    Enabled in wizard
                </label>
            </td>
        </tr>
        </tbody>
    </table>

    <?php submit_button( 'Save add-on' ); ?>
</form>

<hr>

<h2>Existing add-ons</h2>

<div style="display:flex;flex-wrap:wrap;gap:16px;">
    <?php if ( ! empty( $addons ) ) : ?>
        <?php foreach ( $addons as $addon ) : ?>
            <?php
            $name        = $addon['name']        ?? '';
            $description = $addon['description'] ?? '';
            $price       = $addon['price']       ?? 0;
            $duration    = isset( $addon['duration'] ) ? (int) $addon['duration'] : 0;
            $active      = ! empty( $addon['active'] );
            ?>
            <div style="border:1px solid #ddd;border-radius:6px;padding:12px;min-width:220px;max-width:260px;">
                <h3 style="margin:0 0 8px;"><?php echo esc_html( $name ); ?></h3>

                <?php if ( $price > 0 ) : ?>
                    <p style="margin:0 0 4px;">
                        <strong>Price:</strong>
                        <?php echo esc_html( $price ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( $duration > 0 ) : ?>
                    <p style="margin:0 0 4px;">
                        <strong>Time:</strong>
                        <?php echo esc_html( (int) $duration ); ?> min
                    </p>
                <?php endif; ?>

                <?php if ( $description !== '' ) : ?>
                    <p style="margin:4px 0 8px;font-size:13px;color:#555;">
                        <?php echo esc_html( $description ); ?>
                    </p>
                <?php endif; ?>

                <p style="margin:0;font-size:12px;color:#666;">
                    Status: <?php echo $active ? 'Active' : 'Inactive'; ?>
                </p>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p>No add-ons found.</p>
    <?php endif; ?>
</div>
