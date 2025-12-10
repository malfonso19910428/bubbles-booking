<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vista del tab "Google Maps".
 *
 * Variables esperadas:
 *  - string $current_api_key
 */
?>
<form method="post" action="">
    <?php wp_nonce_field( 'bubbles_booking_save_settings' ); ?>

    <h2><?php esc_html_e( 'Google Maps', 'bubbles-booking' ); ?></h2>

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="google_maps_api_key">
                        <?php esc_html_e( 'Google Maps API Key', 'bubbles-booking' ); ?>
                    </label>
                </th>
                <td>
                    <input type="text"
                           name="google_maps_api_key"
                           id="google_maps_api_key"
                           value="<?php echo esc_attr( $current_api_key ); ?>"
                           class="regular-text"
                           style="width: 420px;">
                    <p class="description">
                        <?php
                        esc_html_e(
                            'This key must have Maps JavaScript API and Places API enabled in your Google Cloud project.',
                            'bubbles-booking'
                        );
                        ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <p class="submit">
        <button type="submit" name="bubbles_booking_save_settings" class="button button-primary">
            <?php esc_html_e( 'Save changes', 'bubbles-booking' ); ?>
        </button>
    </p>
</form>
