<?php
if ( ! defined('ABSPATH') ) exit;

$grand_total = isset($view['grand_total']) ? (float) $view['grand_total'] : 0.0;
?>

<h4>Payment</h4>

<style>
  /* ✅ Fuerza que el contenedor tenga altura visible para que Stripe pueda montar */
  #bb-stripe-card-element{
    display:block !important;
    min-height:48px !important;
    padding:12px !important;
    border:1px solid #ddd !important;
    border-radius:6px !important;
    background:#fff !important;
  }

  /* A veces el iframe necesita un poco más de espacio */
  #bb-stripe-card-element .StripeElement{
    width:100% !important;
  }

  /* Mensajes de error */
  #bb-stripe-errors{
    color:#b91c1c;
    margin-top:8px;
  }
</style>

<label for="bb-stripe-card-element">Card</label>
<div id="bb-stripe-card-element"></div>

<div id="bb-stripe-errors" class="bb-error"></div>

<input type="hidden" id="bb_total_amount" value="<?php echo esc_attr($grand_total); ?>">
<input type="hidden" id="bb_payment_done" name="bb_payment_done" value="0">
<input type="hidden" id="bb_payment_intent" name="bb_payment_intent" value="">
<input type="hidden" id="bb_payment_status" name="bb_payment_status" value="">

<?php wp_nonce_field('bb_payment_step', 'bb_payment_nonce'); ?>

<button type="button" id="bb-pay-inline" class="bb-btn bb-btn-primary">
  Pay $<?php echo esc_html( number_format($grand_total, 2) ); ?>
</button>
