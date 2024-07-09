/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait } from '../wait';

/**
 * @typedef {Object} ApplePayOptions
 * @property {string} publishable_key
 * @property {string|null} account_id
 * @property {string} country
 * @property {string} currency
 * @property {string} total_label
 * @property {string} total_amount
 * @property {string} client_secret
 */

wait('#stripe-applepay-payment', () => {
    applePay({
        publishable_key: document.querySelector(
            'meta[name="stripe-publishable-key"]'
        )?.content,
        account_id:
            document.querySelector('meta[name="stripe-account-id"]')?.content ??
            null,
        country: document.querySelector('meta[name="stripe-country"]')?.content,
        currency: document.querySelector('meta[name="stripe-currency"]')
            ?.content,
        total_label: document.querySelector('meta[name="stripe-total-label"]')
            ?.content,
        total_amount: document.querySelector('meta[name="stripe-total-amount"]')
            ?.content,
        client_secret: document.querySelector(
            'meta[name="stripe-client-secret"]'
        )?.content,
    });
});

/**
 * @param {ApplePayOptions} options
 */
function applePay(options) {
    let $options = {
        apiVersion: '2018-05-21',
    };

    if (options.account_id) {
        $options.stripeAccount = options.account_id;
    }

    const stripe = Stripe(options.publishable_key, $options);

    const paymentRequest = stripe.paymentRequest({
        country: options.country,
        currency: options.currency,
        total: {
            label: options.total_label,
            amount: options.total_amount,
        },
        requestPayerName: true,
        requestPayerEmail: true,
    });

    const elements = stripe.elements();
    const prButton = elements.create('paymentRequestButton', {
        paymentRequest: paymentRequest,
    });

    // Check the availability of the Payment Request API first.
    paymentRequest.canMakePayment().then(function (result) {
        if (result) {
            prButton.mount('#payment-request-button');
        } else {
            document.getElementById('payment-request-button').style.display =
                'none';
        }
    });

    paymentRequest.on('paymentmethod', function (ev) {
        // Confirm the PaymentIntent without handling potential next actions (yet).
        stripe
            .confirmCardPayment(
                options.client_secret,
                { payment_method: ev.paymentMethod.id },
                { handleActions: false }
            )
            .then(function (confirmResult) {
                if (confirmResult.error) {
                    // Report to the browser that the payment failed, prompting it to
                    // re-show the payment interface, or show an error message and close
                    // the payment interface.
                    ev.complete('fail');
                } else {
                    // Report to the browser that the confirmation was successful, prompting
                    // it to close the browser payment method collection interface.
                    ev.complete('success');
                    // Check if the PaymentIntent requires any actions and if so let Stripe.js
                    // handle the flow. If using an API version older than "2019-02-11"
                    // instead check for: `paymentIntent.status === "requires_source_action"`.
                    if (
                        confirmResult.paymentIntent.status === 'requires_action'
                    ) {
                        // Let Stripe.js handle the rest of the payment flow.
                        stripe
                            .confirmCardPayment(clientSecret)
                            .then(function (result) {
                                if (result.error) {
                                    // The payment failed -- ask your customer for a new payment method.
                                    handleFailure(result.error);
                                } else {
                                    // The payment has succeeded.
                                    handleSuccess(result);
                                }
                            });
                    } else {
                        // The payment has succeeded.
                    }
                }
            });
    });

    function handleSuccess(result) {
        document.querySelector('input[name="gateway_response"]').value =
            JSON.stringify(result.paymentIntent);

        document.getElementById('server-response').submit();
    }

    function handleFailure(message) {
        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = message;
        errors.hidden = false;
    }
}
