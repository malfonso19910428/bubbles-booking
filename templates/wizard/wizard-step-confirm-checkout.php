<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Step 6 - Confirm & pay (inline Stripe Elements)
 */
$total_display = '$' . number_format_i18n( (float) $bb_total_amount, 2 );
?>

<h3 class="bb-section-title">Step 6 · Confirm &amp; pay</h3>

<p>
    Please review your booking details in the panel on the right.
    Then fill in your contact and card details to complete your booking.
</p>

<form method="post" class="bb-step-form" id="bb-confirm-form" novalidate>
    <?php
    // Mantener el flujo del wizard
    echo $wizard->hidden( 'bb_step', 'confirm' );

    // Reinyectar todos los datos
    echo $wizard->hidden_vehicle_fields();
    echo $wizard->hidden_addons_fields();
    echo $wizard->hidden_address_fields();
    echo $wizard->hidden_date_fields();

    // Paquete seleccionado + notas
    echo $wizard->hidden( 'bb_package', $wizard->posted( 'bb_package' ) );
    echo $wizard->hidden( 'bb_notes',   $wizard->posted( 'bb_notes' ) );

    ?>

    <input type="hidden"
           name="bb_total_amount"
           id="bb_total_amount"
           value="<?php echo esc_attr( $bb_total_amount ); ?>" />

    <input type="hidden"
           name="bb_payment_status"
           id="bb_payment_status"
           value="" />

    <input type="hidden"
           name="bb_payment_intent"
           id="bb_payment_intent"
           value="" />

    <?php
    $bb_name  = $wizard->posted('bb_name');
    $bb_phone = $wizard->posted('bb_phone');
    $bb_email = $wizard->posted('bb_email');
    ?>

    <fieldset class="bb-contact-fieldset" style="margin-top:1rem;">
        <legend><strong>Your contact details</strong></legend>

        <p>
            <label for="bb_name">Name *</label><br>
            <input type="text"
                   id="bb_name"
                   name="bb_name"
                   value="<?php echo esc_attr( $bb_name ); ?>"
                   required
                   class="bb-input bb-input-text" />
        </p>

        <p>
            <label for="bb_phone">Phone *</label><br>
            <input type="tel"
                   id="bb_phone"
                   name="bb_phone"
                   value="<?php echo esc_attr( $bb_phone ); ?>"
                   required
                   class="bb-input bb-input-text" />
        </p>

        <p>
            <label for="bb_email">Email *</label><br>
            <input type="email"
                   id="bb_email"
                   name="bb_email"
                   value="<?php echo esc_attr( $bb_email ); ?>"
                   required
                   class="bb-input bb-input-text" />
        </p>
    </fieldset>

    <fieldset class="bb-payment-fieldset" style="margin-top:1rem;">
        <legend><strong>Payment details</strong></legend>

        <p>
            <strong>Total:</strong>
            <span id="bb-total-label"><?php echo esc_html( $total_display ); ?></span>
        </p>

        <p>
            <label for="bb-stripe-card-element">Card</label>
            <div id="bb-stripe-card-element"
                 style="padding:10px;border:1px solid #ddd;border-radius:4px;"></div>
            <div id="bb-stripe-errors" style="color:#b91c1c;margin-top:8px;"></div>
        </p>
    </fieldset>

    <div class="bb-actions" style="margin-top:1.5rem;">
        <button type="submit"
                name="bb_back"
                value="1"
                class="bb-btn bb-btn-secondary">
            &laquo; Back
        </button>

        <button type="button"
                id="bb-pay-inline"
                class="bb-btn bb-btn-primary">
            Confirm &amp; pay &raquo;
        </button>
    </div>
</form>
