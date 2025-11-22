<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: Step 6 - Confirm & pay
 */
?>

<h3 class="bb-section-title">Step 6 · Confirm & pay</h3>

<p>
    Please review your booking details in the panel on the right.
    When everything looks correct, click <strong>Confirm &amp; pay</strong>
    to go to our secure checkout.
</p>

<form method="post" class="bb-step-form" novalidate>
    <?php
    // Mantener el flujo del wizard
    echo $wizard->hidden('bb_step', 'confirm');

    // Reinyectar todos los datos que ya tenemos
    echo $wizard->hidden_vehicle_fields();
    echo $wizard->hidden_addons_fields();
    echo $wizard->hidden_address_fields();
    echo $wizard->hidden_date_fields();
    echo $wizard->hidden_contact_fields();
    ?>

    <div class="bb-actions">
        <button type="submit"
                name="bb_back"
                value="1"
                class="bb-btn bb-btn-secondary">
            &laquo; Back
        </button>

        <button type="submit"
                name="bb_confirm_booking"
                value="1"
                class="bb-btn bb-btn-primary">
            Confirm &amp; pay &raquo;
        </button>
    </div>
</form>
