(function () {

  function initStripePaymentStep() {

    if (typeof Stripe === 'undefined') { console.log('[BB] Stripe undefined'); return false; }
    if (typeof BBStripeData === 'undefined' || !BBStripeData.publishableKey) { console.log('[BB] BBStripeData missing'); return false; }

    var form        = document.getElementById('bb-wizard-form');
    var payBtn      = document.getElementById('bb-pay-inline');
    var cardDiv     = document.getElementById('bb-stripe-card-element');
    var errorEl     = document.getElementById('bb-stripe-errors');
    var amountInput = document.getElementById('bb_total_amount');

    if (!form || !payBtn || !cardDiv || !amountInput) { console.log('[BB] missing DOM', {form, payBtn, cardDiv, amountInput}); return false; }
    if (cardDiv.offsetHeight === 0) { console.log('[BB] cardDiv not visible yet'); return false; }

    var ds = cardDiv.dataset || {};
    if (ds.bbStripeMounted === '1') return true;
    if (cardDiv.dataset) cardDiv.dataset.bbStripeMounted = '1';

    var stripe   = Stripe(BBStripeData.publishableKey);
    var elements = stripe.elements();
    var card     = elements.create('card');

    try { card.mount(cardDiv); }
    catch (e) {
      console.error('[BB] card.mount error', e);
      if (cardDiv.dataset) cardDiv.dataset.bbStripeMounted = '0';
      return false;
    }

    card.on('change', function (event) {
      if (!errorEl) return;
      errorEl.textContent = event.error ? event.error.message : '';
    });

    function finalizeBookingAfterPay(paymentIntentId) {
      console.log('[BB] finalizeBookingAfterPay start', paymentIntentId);

      if (typeof BBFinalizeData === 'undefined' || !BBFinalizeData.ajaxUrl) {
        return Promise.reject(new Error('Finalize config missing.'));
      }

      var fd = new FormData();
      fd.append('action', BBFinalizeData.action || 'bb_finalize_booking');
      fd.append('nonce',  BBFinalizeData.nonce || '');
      fd.append('payment_intent_id', paymentIntentId || '');

      return fetch(BBFinalizeData.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      })
      .then(function(r){
        console.log('[BB] finalize HTTP', r.status);
        return r.text().then(function(t){
          // Si por alguna razón viene HTML, lo veremos aquí
          console.log('[BB] finalize RAW(0..200)', t.slice(0, 200));
          try { return JSON.parse(t); }
          catch(e){ throw new Error('Finalize returned non-JSON'); }
        });
      })
      .then(function(json){
        console.log('[BB] finalize JSON', json);
        if (!json || !json.success || !json.data || !json.data.redirect) {
          var msg = (json && json.data && json.data.message) ? json.data.message : 'Finalize failed.';
          throw new Error(msg);
        }
        return json.data.redirect;
      });
    }

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

      console.log('[BB] start payment', {amount, name, email, phone});

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
        console.log('[BB] PI response', json);

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
        console.log('[BB] confirm result', result);

        if (!result || result.error) {
          throw new Error((result && result.error && result.error.message) ? result.error.message : 'Payment failed.');
        }

        var pi = result.paymentIntent;
        if (!pi) throw new Error('Payment failed.');

        console.log('[BB] paymentIntent', pi.id, pi.status);

        if (pi.status !== 'succeeded') {
          throw new Error('Payment could not be completed.');
        }

        var doneField = document.getElementById('bb_payment_done');
        var piField   = document.getElementById('bb_payment_intent');
        var stField   = document.getElementById('bb_payment_status');

        if (doneField) doneField.value = '1';
        if (piField)   piField.value = (pi.id || '');
        if (stField)   stField.value = 'paid';

        return finalizeBookingAfterPay(pi.id).then(function(redirectUrl){
          console.log('[BB] redirectUrl', redirectUrl);

          // Redirect robusto
          setTimeout(function(){
            window.location.assign(redirectUrl);
          }, 50);
        });
      })
      .catch(function (err) {
        console.error('[BB] payment flow error', err);
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
