/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class ProcessStripePayment {
    constructor(key, usingToken) {
        this.key = key;
        this.usingToken = usingToken;
    }

    setupStripe() {
        this.stripe = Stripe(this.key);
        this.elements = this.stripe.elements();

        return this;
    }

    createElement() {
        this.cardElement = this.elements.create('card');

        return this;
    }

    mountCardElement() {
        this.cardElement.mount('#card-element');

        return this;
    }

    completePaymentUsingToken() {
        let payNowButton = document.getElementById('pay-now-with-token');

        this.payNowButton = payNowButton;
        this.payNowButton.disabled = true;

        processingOverlay(true);

        this.stripe
            .handleCardPayment(payNowButton.dataset.secret, {
                payment_method: payNowButton.dataset.token,
            })
            .then((result) => {
                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                return this.handleSuccess(result);
            });
    }

    completePaymentWithoutToken() {
        let payNowButton = document.getElementById('pay-now');
        this.payNowButton = payNowButton;

        let cardHolderName = document.getElementById('cardholder-name');

        processingOverlay(true);

        this.stripe
            .handleCardPayment(payNowButton.dataset.secret, this.cardElement, {
                payment_method_data: {
                    billing_details: { name: cardHolderName.value },
                },
            })
            .then((result) => {
                document
                    .getElementById('processing-overlay')
                    .classList.add('hidden');

                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                return this.handleSuccess(result);
            });
    }

    handleSuccess(result) {
        processingOverlay(false);

        document.querySelector(
            'input[name="gateway_response"]'
        ).value = JSON.stringify(result.paymentIntent);

        let tokenBillingCheckbox = document.querySelector(
            'input[name="token-billing-checkbox"]'
        );

        if (tokenBillingCheckbox) {
            document.querySelector('input[name="store_card"]').value =
                tokenBillingCheckbox.checked;
        }

        document.getElementById('server-response').submit();
    }

    handleFailure(message) {
        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = message;
        errors.hidden = false;

        processingOverlay(false);
        this.payNowButton.disabled = false;
    }

    handle() {
        this.setupStripe();

        if (this.usingToken) {
            document
                .getElementById('pay-now-with-token')
                .addEventListener('click', () => {
                    return this.completePaymentUsingToken();
                });
        }

        if (!this.usingToken) {
            this.createElement().mountCardElement();

            document.getElementById('pay-now').addEventListener('click', () => {
                return this.completePaymentWithoutToken();
            });
        }
    }
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
).content;

const usingToken = document.querySelector('meta[name="using-token"]').content;

new ProcessStripePayment(publishableKey, usingToken).handle();
