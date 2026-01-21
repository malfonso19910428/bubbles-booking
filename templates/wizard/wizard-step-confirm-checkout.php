<?php
/**
 * Wizard Step 6: Confirm & Contact (STATE-driven)
 *
 * - Lee valores desde $state (persistido por draft service).
 * - Captura datos del cliente (name, phone, email, notes).
 * - Envía bb_confirm_booking + nonce.
 *
 * Variables disponibles desde Wizard Shell:
 * - $step_data  -> datos del step (opcional)
 * - $errors     -> array de errores (lista)
 * - $state      -> estado completo del wizard (array)
 * - $summary    -> viewmodel del summary (ya se muestra en sidebar)
 */

if ( ! defined('ABSPATH') ) exit;

$state = isset($state) && is_array($state) ? $state : array();

// Prefill desde state (para volver atrás y no perder datos)
$cust  = isset($state['customer']) && is_array($state['customer']) ? $state['customer'] : array();
$name  = $cust['name']  ?? '';
$phone = $cust['phone'] ?? '';
$email = $cust['email'] ?? '';
$notes = $cust['notes'] ?? '';

// Errores (tu shell usa $errors como lista; si más adelante los keyeas por step, se adapta fácil)
$errors_list = isset($errors) && is_array($errors) ? $errors : array();
?>
<h3 class="bb-section-title">Step 6 · Review &amp; contact</h3>

<?php if ( ! empty($errors_list) ) : ?>
  <div class="bb-error" style="margin-bottom:12px;">
    <?php foreach ( $errors_list as $msg ) : ?>
      <div><?php echo esc_html( (string) $msg ); ?></div>
    <?php endforeach; ?>
  </div>
<?php else : ?>
  <p>Please review your details and tell us how to contact you.</p>
<?php endif; ?>

<form method="post" class="bb-step-form bb-step-confirm" novalidate>

  <!-- Step actual -->
  <input type="hidden" name="bb_step" value="confirm">

  <!-- Nonce para submit final -->
  <?php wp_nonce_field('bb_confirm_booking', 'bb_confirm_nonce'); ?>

  <div class="bb-confirm-layout">

    <div class="bb-confirm-contact-panel">

      <h4>Contact details</h4>

      <p>
        <label>
          <strong>Name *</strong><br>
          <input type="text"
                 name="bb_name"
                 value="<?php echo esc_attr( $name ); ?>"
                 required
                 style="width:100%;max-width:420px;">
        </label>
      </p>

      <p>
        <label>
          <strong>Mobile phone *</strong><br>
          <input type="tel"
                 name="bb_phone"
                 value="<?php echo esc_attr( $phone ); ?>"
                 required
                 style="width:100%;max-width:420px;">
        </label>
      </p>

      <p>
        <label>
          <strong>Email *</strong><br>
          <input type="email"
                 name="bb_email"
                 value="<?php echo esc_attr( $email ); ?>"
                 required
                 style="width:100%;max-width:420px;">
        </label>
      </p>

      <p>
        <label>
          <strong>Notes (optional)</strong><br>
          <textarea name="bb_notes"
                    rows="4"
                    style="width:100%;max-width:420px;"><?php echo esc_textarea( $notes ); ?></textarea>
        </label>
      </p>

    </div>

    <?php
    // Nota: el resumen ya lo muestra tu sidebar con BB_Summary_Controller.
    // Si quieres un mini-resumen adicional dentro del step, se puede renderizar aquí.
    ?>

  </div>

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
      Confirm &amp; book
    </button>

  </div>

</form>
