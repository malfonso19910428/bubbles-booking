<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Step 4 - Address
 *
 * Usa:
 *  - $step_data (array)  -> datos del paso "address" desde BB_Address_Controller::get_view_data()
 *  - $errors    (array)  -> errores globales del wizard (por step) desde BB_Wizard_Shell
 */

// Normalizar datos del paso
$step_data = isset( $step_data ) && is_array( $step_data ) ? $step_data : array();

// Valores del estado
$address_type  = $step_data['address_type']  ?? '';
$address       = $step_data['address']       ?? '';
$address_extra = $step_data['address_extra'] ?? '';

$city      = $step_data['city']      ?? '';
$state_val = $step_data['state']     ?? '';
$zip       = $step_data['zip']       ?? '';

$place_id = $step_data['place_id'] ?? '';
$street   = $step_data['street']   ?? '';
$lat      = $step_data['lat']      ?? '';
$lng      = $step_data['lng']      ?? '';

// Errores desde el shell
$errors    = isset( $errors ) && is_array( $errors ) ? $errors : array();
$php_error = $errors['address'] ?? '';
?>



<?php if ( ! empty( $php_error ) ) : ?>
    <p class="bb-error" style="color:#b91c1c;">
        <?php echo esc_html( $php_error ); ?>
    </p>
<?php else : ?>
    <p>Please enter the address where we will work.</p>
<?php endif; ?>

<!-- IMPORTANTE: el <form> y los botones Back/Continue viven en el shell.
     Aquí solo van los campos del step. -->

<!-- Identificador de paso para el controlador -->
<input type="hidden" name="bb_step" value="address" />

<!-- Address type -->
<p><strong>Address type</strong><br>
    <label>
        <input
            type="radio"
            name="bb_address_type"
            value="home"
            <?php checked( $address_type, 'home' ); ?>
        >
        Home
    </label>
    <label>
        <input
            type="radio"
            name="bb_address_type"
            value="work"
            <?php checked( $address_type, 'work' ); ?>
        >
        Work
    </label>
    <label>
        <input
            type="radio"
            name="bb_address_type"
            value="other"
            <?php checked( $address_type, 'other' ); ?>
        >
        Other
    </label>
</p>

<!-- Error de validación manejado por JS (cliente).
     PHP muestra su error arriba; este div queda libre para mensajes del JS si quieres. -->
<div id="bb_address_error"
     class="bb-error"
     style="color:#b91c1c;margin-top:8px;">
</div>

<!-- Campo principal de dirección con Autocomplete -->
<p>
    <label><strong>Service address</strong><br>
        <input
            type="text"
            id="bb_address_input"
            name="bb_address"
            value="<?php echo esc_attr( $address ); ?>"
            placeholder="Start typing your address..."
            required
            style="width:100%;max-width:480px;"
        >
    </label>
</p>

<!-- Hidden Google fields: usados por tu JS y por el controlador PHP -->
<input type="hidden" name="bb_place_id"       value="<?php echo esc_attr( $place_id ); ?>">
<input type="hidden" name="bb_address_street" value="<?php echo esc_attr( $street ); ?>">
<input type="hidden" name="bb_address_city"   value="<?php echo esc_attr( $city ); ?>">
<input type="hidden" name="bb_address_state"  value="<?php echo esc_attr( $state_val ); ?>">
<input type="hidden" name="bb_address_zip"    value="<?php echo esc_attr( $zip ); ?>">
<input type="hidden" name="bb_address_lat"    value="<?php echo esc_attr( $lat ); ?>">
<input type="hidden" name="bb_address_lng"    value="<?php echo esc_attr( $lng ); ?>">

<!-- Preview que también puede actualizar el JS -->
<div id="bb_address_preview" style="font-size:13px;color:#4b5563;margin-top:4px;">
    <?php
    $preview_parts = array_filter( array( $city, $state_val, $zip ) );
    if ( ! empty( $preview_parts ) ) {
        echo 'Detected: ' . esc_html( implode( ', ', $preview_parts ) );
    }
    ?>
</div>

<!-- Notas adicionales -->
<p>
    <label><strong>Notes</strong><br>
        <input
            type="text"
            name="bb_address_extra"
            value="<?php echo esc_attr( $address_extra ); ?>"
            placeholder="gate code, etc."
            style="width:100%;max-width:480px;"
        >
    </label>
</p>
