/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

class ProcessPromptPay {
    constructor(key, secret, stripeConnect) {
        this.key = key;
        this.secret = secret;
        this.stripeConnect = stripeConnect;

        this.errors = document.getElementById('errors');
    }

    setupStripe() {
        if (this.stripeConnect) {
            this.stripe = Stripe(this.key, {
                stripeAccount: this.stripeConnect,
            });
        } else {
            this.stripe = Stripe(this.key);
        }

        return this;
    }

    setButtonLoading(loading) {
        const payNowButton = document.getElementById('pay-now');

        payNowButton.disabled = loading;
        payNowButton.querySelector('svg').classList.toggle('hidden', loading);
        payNowButton.querySelector('span').classList.toggle('hidden', !loading);
    }

    handleFailure(message) {
        this.errors.textContent = '';
        this.errors.textContent = message;
        this.errors.hidden = false;

        this.setButtonLoading(false);
    }

    handleSubmit() {
        const email = document.getElementById('promptpay-email').value ?? '';

        this.setButtonLoading(true);

        this.stripe
            .confirmPromptPayPayment(this.secret, {
                payment_method: {
                    type: 'promptpay',
                    billing_details: {
                        email: email,
                    },
                },
            })
            .then((result) => {
                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                const paymentIntent = result.paymentIntent;

                if (
                    paymentIntent &&
                    ['succeeded', 'processing'].includes(paymentIntent.status)
                ) {
                    document.querySelector(
                        'input[name="gateway_response"]'
                    ).value = JSON.stringify(paymentIntent);

                    document.getElementById('server-response').submit();

                    return;
                }

                return this.handleFailure(
                    paymentIntent && paymentIntent.status === 'canceled'
                        ? 'PromptPay payment was cancelled.'
                        : 'PromptPay payment could not be completed.'
                );
            })
            .catch((error) => this.handleFailure(error.message));
    }

    handle() {
        document
            .getElementById('pay-now')
            .addEventListener('click', () => this.handleSubmit());
    }
}

function boot() {
    const publishableKey =
        document.querySelector('meta[name="stripe-publishable-key"]')
            ?.content ?? '';

    const secret =
        document.querySelector('meta[name="stripe-client-secret"]')?.content ??
        '';

    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

    new ProcessPromptPay(publishableKey, secret, stripeConnect)
        .setupStripe()
        .handle();
}

instant() ? boot() : wait('#stripe-promptpay-payment').then(() => boot());
