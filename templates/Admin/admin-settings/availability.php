<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Availability Rules – Settings Tab
 *
 * Espera:
 * - $rules (array)
 * - $saved (bool)
 * - $errors (array)
 */

$rules  = $rules  ?? array();
$saved  = ! empty( $saved );
$errors = $errors ?? array();
?>

<h2 class="title">Availability Rules</h2>
<p class="description">
    Configure global rules that control technician availability and booking slots.
</p>

<?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible">
        <p><strong>Saved.</strong> Availability rules updated successfully.</p>
    </div>
<?php endif; ?>

<?php if ( ! empty( $errors ) ) : ?>
    <div class="notice notice-error">
        <ul>
            <?php foreach ( $errors as $error ) : ?>
                <li><?php echo esc_html( $error ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post">
    <?php wp_nonce_field( 'bb_save_av_rules', 'bb_av_rules_nonce' ); ?>

    <table class="form-table" role="presentation">
        <tbody>

        <tr>
            <th scope="row">
                <label for="buffer_minutes">Buffer Between Jobs (minutes)</label>
            </th>
            <td>
                <input type="number"
                       name="buffer_minutes"
                       id="buffer_minutes"
                       min="0"
                       max="240"
                       step="5"
                       value="<?php echo esc_attr( $rules['buffer_minutes'] ?? 30 ); ?>">
                <p class="description">
                    Minimum time between jobs for preparation or travel.
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="min_start_time">Minimum Start Time</label>
            </th>
            <td>
                <input type="time"
                       name="min_start_time"
                       id="min_start_time"
                       value="<?php echo esc_attr( $rules['min_start_time'] ?? '08:00' ); ?>">
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="max_start_time">Maximum Start Time</label>
            </th>
            <td>
                <input type="time"
                       name="max_start_time"
                       id="max_start_time"
                       value="<?php echo esc_attr( $rules['max_start_time'] ?? '18:00' ); ?>">
                <p class="description">
                    Last allowed time to start a service.
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="max_jobs_per_day">Max Jobs per Technician / Day</label>
            </th>
            <td>
                <input type="number"
                       name="max_jobs_per_day"
                       id="max_jobs_per_day"
                       min="1"
                       max="20"
                       value="<?php echo esc_attr( $rules['max_jobs_per_day'] ?? 3 ); ?>">
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="min_advance_hours">Minimum Advance Booking (hours)</label>
            </th>
            <td>
                <input type="number"
                       name="min_advance_hours"
                       id="min_advance_hours"
                       min="0"
                       max="720"
                       value="<?php echo esc_attr( $rules['min_advance_hours'] ?? 24 ); ?>">
                <p class="description">
                    Prevents last-minute bookings.
                </p>
            </td>
        </tr>

        </tbody>
    </table>

    <p class="submit">
        <button type="submit"
                name="bb_av_rules_submit"
                class="button button-primary">
            Save Availability Rules
        </button>
    </p>
</form>
