(function () {
    document.addEventListener('DOMContentLoaded', function () {

        if (typeof Stripe === 'undefined') {
            return;
        }
        if (typeof BBStripeData === 'undefined' || !BBStripeData.publishableKey) {
            return;
        }

        var form    = document.getElementById('bb-confirm-form');
        var payBtn  = document.getElementById('bb-pay-inline');
        var cardDiv = document.getElementById('bb-stripe-card-element');
        var errorEl = document.getElementById('bb-stripe-errors');
        var amountInput = document.getElementById('bb_total_amount');

        if (!form || !payBtn || !cardDiv || !amountInput) {
            return;
        }

        var stripe   = Stripe(BBStripeData.publishableKey);
        var elements = stripe.elements();
        var card     = elements.create('card');

        card.mount('#bb-stripe-card-element');

        card.on('change', function (event) {
            if (event.error) {
                errorEl.textContent = event.error.message;
            } else {
                errorEl.textContent = '';
            }
        });

        payBtn.addEventListener('click', function (e) {
            e.preventDefault();

            errorEl.textContent = '';

            var amount = parseFloat(amountInput.value || '0');
            if (!amount || amount <= 0) {
                errorEl.textContent = 'Invalid total amount.';
                return;
            }

            var nameEl  = document.getElementById('bb_name');
            var emailEl = document.getElementById('bb_email');
            var phoneEl = document.getElementById('bb_phone');

            var name  = nameEl  ? nameEl.value  : '';
            var email = emailEl ? emailEl.value : '';
            var phone = phoneEl ? phoneEl.value : '';

            if (!name || !email || !phone) {
                errorEl.textContent = 'Please fill in name, phone and email before paying.';
                return;
            }

            payBtn.disabled  = true;
            payBtn.textContent = 'Processing...';

            var data = new FormData();
            data.append('action', 'bb_create_payment_intent');
            data.append('amount', amount);
            data.append('currency', BBStripeData.currency || 'usd');

            fetch(BBStripeData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (json) {
                    if (!json || !json.success || !json.data || !json.data.client_secret) {
                        var msg = (json && json.data && json.data.message)
                            ? json.data.message
                            : 'Unable to create payment.';
                        throw new Error(msg);
                    }

                    var clientSecret    = json.data.client_secret;
                    var paymentIntentId = json.data.payment_intent_id;

                    return stripe.confirmCardPayment(clientSecret, {
                        payment_method: {
                            card: card,
                            billing_details: {
                                name:  name,
                                email: email,
                                phone: phone
                            }
                        }
                    }).then(function (result) {
                        if (result.error) {
                            throw new Error(result.error.message || 'Payment failed.');
                        }

                        if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                            var statusField = document.getElementById('bb_payment_status');
                            var piField     = document.getElementById('bb_payment_intent');

                            if (statusField) statusField.value = 'paid';
                            if (piField && paymentIntentId) piField.value = paymentIntentId;

                            var hidden = document.createElement('input');
                            hidden.type  = 'hidden';
                            hidden.name  = 'bb_confirm_booking';
                            hidden.value = '1';
                            form.appendChild(hidden);

                            form.submit();
                        } else {
                            throw new Error('Payment was not completed.');
                        }
                    });
                })
                .catch(function (err) {
                    errorEl.textContent = err.message || 'Payment error.';
                    payBtn.disabled     = false;
                    payBtn.textContent  = 'Confirm & pay »';
                });
        });
    });
})();
