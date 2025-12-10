<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array $bb_services_list_local */
$services = is_array( $bb_services_list_local ) ? $bb_services_list_local : array();
?>

<h2>Add new service</h2>

<form method="post">
    <?php wp_nonce_field( 'bb_services_add' ); ?>
    <input type="hidden" name="bb_services_action" value="add">

    <table class="form-table" role="presentation">
        <tbody>
        <tr>
            <th scope="row"><label for="service_name">Name</label></th>
            <td><input type="text" name="service_name" id="service_name" class="regular-text" required></td>
        </tr>
        <tr>
            <th scope="row"><label for="service_description">Description</label></th>
            <td><textarea name="service_description" id="service_description" class="large-text" rows="3"></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="service_price">Base price</label></th>
            <td><input type="number" step="0.01" min="0" name="service_price" id="service_price" class="small-text"></td>
        </tr>
        <tr>
            <th scope="row"><label for="service_duration">Duration (min)</label></th>
            <td><input type="number" step="1" min="0" name="service_duration" id="service_duration" class="small-text"></td>
        </tr>
        <tr>
            <th scope="row">Active</th>
            <td>
                <label>
                    <input type="checkbox" name="service_active" value="1" checked>
                    Enabled in wizard
                </label>
            </td>
        </tr>
        </tbody>
    </table>

    <?php submit_button( 'Save service' ); ?>
</form>

<hr>

<h2>Existing services</h2>

<div style="display:flex;flex-wrap:wrap;gap:16px;">
    <?php if ( ! empty( $services ) ) : ?>
        <?php foreach ( $services as $service ) : ?>
            <?php
            $name        = $service['name']        ?? '';
            $description = $service['description'] ?? '';
            $price       = $service['price']       ?? 0;
            $duration    = isset( $service['duration'] ) ? (int) $service['duration'] : 0;
            $active      = ! empty( $service['active'] );
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
        <p>No services found.</p>
    <?php endif; ?>
</div>
