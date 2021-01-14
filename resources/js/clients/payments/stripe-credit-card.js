/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class StripeCreditCard {
    constructor(key, token, secret, onlyAuthorization) {
        this.key = key;
        this.token = token;
        this.secret = secret;
        this.onlyAuthorization = onlyAuthorization;
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

        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        this.stripe
            .handleCardPayment(this.secret, {
                payment_method: this.token,
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

        this.payNowButton.disabled = true;

        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        let cardHolderName = document.getElementById('cardholder-name');

        this.stripe
            .handleCardPayment(this.secret, this.cardElement, {
                payment_method_data: {
                    billing_details: { name: cardHolderName.value },
                },
            })
            .then((result) => {
                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                return this.handleSuccess(result);
            });
    }

    handleSuccess(result) {
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

        this.payNowButton.disabled = false;
        this.payNowButton.querySelector('svg').classList.add('hidden');
        this.payNowButton.querySelector('span').classList.remove('hidden');
    }

    handleAuthorization() {
        let cardHolderName = document.getElementById('cardholder-name');

        let payNowButton = document.getElementById('authorize-card');

        this.payNowButton = payNowButton;
        this.payNowButton.disabled = true;

        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        this.stripe
            .handleCardSetup(this.secret, this.cardElement, {
                payment_method_data: {
                    billing_details: { name: cardHolderName.value },
                },
            })
            .then((result) => {
                if (result.error) {
                    return this.handleFailure(result);
                }

                return this.handleSuccessfulAuthorization(result);
            });
    }

    handleSuccessfulAuthorization(result) {
        document.getElementById('gateway_response').value = JSON.stringify(
            result.setupIntent
        );

        document.getElementById('server_response').submit();
    }

    handle() {
        this.setupStripe();

        if (this.onlyAuthorization) {
            this.createElement().mountCardElement();

            document
                .getElementById('authorize-card')
                .addEventListener('click', () => {
                    return this.handleAuthorization();
                });
        } else {
            if (this.token) {
                document
                    .getElementById('pay-now-with-token')
                    .addEventListener('click', () => {
                        return this.completePaymentUsingToken();
                    });
            }

            if (!this.token) {
                this.createElement().mountCardElement();

                document
                    .getElementById('pay-now')
                    .addEventListener('click', () => {
                        return this.completePaymentWithoutToken();
                    });
            }
        }
    }
}

const publishableKey =
    document.querySelector('meta[name="stripe-publishable-key"]').content ?? '';

const token = document.querySelector('meta[name="stripe-token"]').content ?? '';

const secret =
    document.querySelector('meta[name="stripe-secret"]').content ?? '';

const onlyAuthorization =
    document.querySelector('meta[name="only-authorization"]').content ?? '';

new StripeCreditCard(publishableKey, token, secret, onlyAuthorization).handle();
