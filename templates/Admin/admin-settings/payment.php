<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vista del tab "Stripe / Payments".
 *
 * Variables esperadas:
 *  - string $stripe_mode
 *  - string $stripe_secret_key
 *  - string $stripe_public_key
 *  - string $stripe_currency
 */
?>
<form method="post" action="">
    <?php wp_nonce_field( 'bubbles_booking_save_stripe_settings' ); ?>

    <h2><?php esc_html_e( 'Stripe Payments', 'bubbles-booking' ); ?></h2>

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="stripe_mode">
                        <?php esc_html_e( 'Mode', 'bubbles-booking' ); ?>
                    </label>
                </th>
                <td>
                    <select name="stripe_mode" id="stripe_mode">
                        <option value="test" <?php selected( $stripe_mode, 'test' ); ?>>
                            <?php esc_html_e( 'Test', 'bubbles-booking' ); ?>
                        </option>
                        <option value="live" <?php selected( $stripe_mode, 'live' ); ?>>
                            <?php esc_html_e( 'Live', 'bubbles-booking' ); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Use Test while you are configuring and testing your integration.', 'bubbles-booking' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="stripe_secret_key">
                        <?php esc_html_e( 'Stripe Secret Key', 'bubbles-booking' ); ?>
                    </label>
                </th>
                <td>
                    <input type="text"
                           name="stripe_secret_key"
                           id="stripe_secret_key"
                           value="<?php echo esc_attr( $stripe_secret_key ); ?>"
                           class="regular-text"
                           style="width: 420px;">
                    <p class="description">
                        <?php esc_html_e( 'Your Secret key from Stripe (e.g. sk_test_XXXX or sk_live_XXXX).', 'bubbles-booking' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="stripe_public_key">
                        <?php esc_html_e( 'Stripe Publishable Key', 'bubbles-booking' ); ?>
                    </label>
                </th>
                <td>
                    <input type="text"
                           name="stripe_public_key"
                           id="stripe_public_key"
                           value="<?php echo esc_attr( $stripe_public_key ); ?>"
                           class="regular-text"
                           style="width: 420px;">
                    <p class="description">
                        <?php esc_html_e( 'Your Publishable key from Stripe (e.g. pk_test_XXXX or pk_live_XXXX).', 'bubbles-booking' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="stripe_currency">
                        <?php esc_html_e( 'Currency', 'bubbles-booking' ); ?>
                    </label>
                </th>
                <td>
                    <input type="text"
                           name="stripe_currency"
                           id="stripe_currency"
                           value="<?php echo esc_attr( $stripe_currency ); ?>"
                           class="regular-text"
                           style="width: 120px;">
                    <p class="description">
                        <?php esc_html_e( '3-letter currency code (e.g. usd, cad, eur).', 'bubbles-booking' ); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <p class="submit">
        <button type="submit" name="bb_stripe_save_settings" class="button button-primary">
            <?php esc_html_e( 'Save Stripe settings', 'bubbles-booking' ); ?>
        </button>
    </p>
</form>
