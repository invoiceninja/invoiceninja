/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class AuthorizeStripeCard {
    constructor(key) {
        this.key = key;
        this.cardHolderName = document.getElementById('cardholder-name');
        this.cardButton = document.getElementById('card-button');
        this.clientSecret = this.cardButton.dataset.secret;
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

    handleStripe(stripe, cardHolderName) {
        this.cardButton.disabled = true;
        this.cardButton.querySelector('span').classList.add('hidden');
        this.cardButton.querySelector('svg').classList.remove('hidden');

        stripe
            .handleCardSetup(this.clientSecret, this.cardElement, {
                payment_method_data: {
                    billing_details: { name: cardHolderName.value },
                },
            })
            .then((result) => {
                if (result.error) {
                    return this.handleFailure(result);
                }

                return this.handleSuccess(result);
            });
    }

    handleFailure(result) {
        this.cardButton.disabled = false;
        this.cardButton.querySelector('span').classList.remove('hidden');
        this.cardButton.querySelector('svg').classList.add('hidden');

        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = result.error.message;
        errors.hidden = false;
    }

    handleSuccess(result) {
        document.getElementById('gateway_response').value = JSON.stringify(
            result.setupIntent
        );
        document.getElementById('is_default').value = document.getElementById(
            'proxy_is_default'
        ).checked;

        processingOverlay(false);

        document.getElementById('server_response').submit();
    }

    handle() {
        this.setupStripe()
            .createElement()
            .mountCardElement();

        this.cardButton.addEventListener('click', () => {
            this.handleStripe(this.stripe, this.cardHolderName);
        });

        return this;
    }
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
).content;

/** @handle */
new AuthorizeStripeCard(publishableKey).handle();
