<?php
if ( ! defined('ABSPATH') ) exit;

$state = ( isset($state) && is_array($state) ) ? $state : array();
$cust  = ( isset($state['customer']) && is_array($state['customer']) ) ? $state['customer'] : array();

$name  = (string) ( $cust['name']  ?? '' );
$phone = (string) ( $cust['phone'] ?? '' );
$email = (string) ( $cust['email'] ?? '' );
$notes = (string) ( $cust['notes'] ?? '' );
?>

<p>Please review your details and tell us how to contact you.</p>

<?php wp_nonce_field('bb_confirm_booking', 'bb_confirm_nonce'); ?>

<div class="bb-confirm-layout">
  <div class="bb-confirm-contact-panel">
    <h4>Contact details</h4>

    <p><label><strong>Name *</strong><br>
      <input type="text" id="bb_name" name="bb_name" value="<?php echo esc_attr($name); ?>" required style="width:100%;max-width:420px;">
    </label></p>

    <p><label><strong>Mobile phone *</strong><br>
      <input type="tel" id="bb_phone" name="bb_phone" value="<?php echo esc_attr($phone); ?>" required style="width:100%;max-width:420px;">
    </label></p>

    <p><label><strong>Email *</strong><br>
      <input type="email" id="bb_email" name="bb_email" value="<?php echo esc_attr($email); ?>" required style="width:100%;max-width:420px;">
    </label></p>

    <p><label><strong>Notes (optional)</strong><br>
      <textarea id="bb_notes" name="bb_notes" rows="4" style="width:100%;max-width:420px;"><?php echo esc_textarea($notes); ?></textarea>
    </label></p>

  </div>
</div>
