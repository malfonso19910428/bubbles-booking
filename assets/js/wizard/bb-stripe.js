(function () {

  function initStripePaymentStep() {

    if (typeof Stripe === 'undefined') return false;
    if (typeof BBStripeData === 'undefined' || !BBStripeData.publishableKey) return false;

    var form        = document.getElementById('bb-wizard-form');
    var payBtn      = document.getElementById('bb-pay-inline');
    var cardDiv     = document.getElementById('bb-stripe-card-element');
    var errorEl     = document.getElementById('bb-stripe-errors');
    var amountInput = document.getElementById('bb_total_amount');

    if (!form || !payBtn || !cardDiv || !amountInput) return false;

    // Si el contenedor no es visible, Stripe no se monta bien
    if (cardDiv.offsetHeight === 0) return false;

    // dataset puede no existir en browsers viejos (raro, pero seguro)
    var ds = cardDiv.dataset || {};
    if (ds.bbStripeMounted === '1') return true;

    // Marca como montado
    if (cardDiv.dataset) cardDiv.dataset.bbStripeMounted = '1';

    var stripe   = Stripe(BBStripeData.publishableKey);
    var elements = stripe.elements();
    var card     = elements.create('card');

    try {
      card.mount(cardDiv);
    } catch (e) {
      // Silencioso: permitimos reintento
      if (cardDiv.dataset) cardDiv.dataset.bbStripeMounted = '0';
      return false;
    }

    card.on('change', function (event) {
      if (!errorEl) return;
      errorEl.textContent = event.error ? event.error.message : '';
    });

    // Evita duplicar listener
    if (!payBtn.dataset) payBtn.dataset = {};
    if (payBtn.dataset.bbStripeBound === '1') return true;
    payBtn.dataset.bbStripeBound = '1';

    payBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (errorEl) errorEl.textContent = '';

      var amount = parseFloat(amountInput.value || '0');
      if (!amount || amount <= 0) {
        if (errorEl) errorEl.textContent = 'Please check your total and try again.';
        return;
      }

      var nameEl  = document.getElementById('bb_name');
      var emailEl = document.getElementById('bb_email');
      var phoneEl = document.getElementById('bb_phone');

      var name  = nameEl  ? (nameEl.value  || '') : '';
      var email = emailEl ? (emailEl.value || '') : '';
      var phone = phoneEl ? (phoneEl.value || '') : '';

      payBtn.disabled = true;
      var originalText = payBtn.textContent;
      payBtn.textContent = 'Processing...';

      var data = new FormData();
      data.append('action', 'bb_create_payment_intent');
      data.append('amount', String(amount));
      data.append('currency', (BBStripeData.currency || 'usd'));
      if (BBStripeData.nonce) data.append('nonce', BBStripeData.nonce);

      fetch(BBStripeData.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
      })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json || !json.success || !json.data || !json.data.client_secret) {
          var msg = (json && json.data && json.data.message) ? json.data.message : 'Payment could not be started.';
          throw new Error(msg);
        }

        return stripe.confirmCardPayment(json.data.client_secret, {
          payment_method: {
            card: card,
            billing_details: { name: name, email: email, phone: phone }
          }
        });
      })
      .then(function (result) {
        if (!result || result.error) {
          throw new Error((result && result.error && result.error.message) ? result.error.message : 'Payment failed.');
        }

        var pi = result.paymentIntent;
        if (!pi) throw new Error('Payment failed.');

        if (pi.status !== 'succeeded' && pi.status !== 'processing') {
          throw new Error('Payment could not be completed.');
        }

        var doneField = document.getElementById('bb_payment_done');
        var piField   = document.getElementById('bb_payment_intent');
        var stField   = document.getElementById('bb_payment_status');

        if (doneField) doneField.value = '1';
        if (piField)   piField.value = (pi.id || '');
        if (stField)   stField.value = (pi.status === 'succeeded') ? 'paid' : pi.status;

        form.submit();
      })
      .catch(function (err) {
        if (errorEl) {
          errorEl.textContent = (err && err.message) ? err.message : 'Payment error.';
        }
        payBtn.disabled = false;
        payBtn.textContent = originalText || 'Pay';
      });

    });

    return true;
  }

  function tryInit(times) {
    if (initStripePaymentStep()) return;
    if (times <= 0) return;
    setTimeout(function () { tryInit(times - 1); }, 250);
  }

  document.addEventListener('DOMContentLoaded', function () {
    tryInit(20);
  });

})();
