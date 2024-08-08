/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

import { wait, instant } from '../wait';

class StripeBrowserPay {
    constructor() {
        this.clientSecret = document.querySelector(
            'meta[name=stripe-pi-client-secret]'
        )?.content;
    }

    init() {
        let config = {};

        if (document.querySelector('meta[name=stripe-account-id]')) {
            config.apiVersion = '2020-08-27';

            config.stripeAccount = document.querySelector(
                'meta[name=stripe-account-id]'
            )?.content;
        }

        this.stripe = Stripe(
            document.querySelector('meta[name=stripe-publishable-key]')
                ?.content,
            config
        );

        this.elements = this.stripe.elements();

        return this;
    }

    createPaymentRequest() {
        this.paymentRequest = this.stripe.paymentRequest(
            JSON.parse(
                document.querySelector('meta[name=payment-request-data').content
            )
        );

        return this;
    }

    createPaymentRequestButton() {
        this.paymentRequestButton = this.elements.create(
            'paymentRequestButton',
            {
                paymentRequest: this.paymentRequest,
            }
        );
    }

    handlePaymentRequestEvents(stripe, clientSecret) {
        document.querySelector('#errors').hidden = true;

        this.paymentRequest.on('paymentmethod', function (ev) {
            stripe
                .confirmCardPayment(
                    clientSecret,
                    { payment_method: ev.paymentMethod.id },
                    { handleActions: false }
                )
                .then(function (confirmResult) {
                    if (confirmResult.error) {
                        
                        document.querySelector('#errors').innerText =
                            confirmResult.error.message;
                        document.querySelector('#errors').hidden = false;

                        ev.complete('fail');
                    } else {
                        ev.complete('success');

                        if (
                            confirmResult.paymentIntent.status ===
                            'requires_action'
                        ) {
                            stripe
                                .confirmCardPayment(clientSecret)
                                .then(function (result) {
                                    if (result.error) {
                                        ev.complete('fail');

                                        document.querySelector(
                                            '#errors'
                                        ).innerText = result.error.message;

                                        document.querySelector(
                                            '#errors'
                                        ).hidden = false;
                                    } else {
                                        document.querySelector(
                                            'input[name="gateway_response"]'
                                        ).value = JSON.stringify(
                                            result.paymentIntent
                                        );

                                        document
                                            .getElementById('server-response')
                                            .submit();
                                    }
                                });
                        } else {
                            document.querySelector(
                                'input[name="gateway_response"]'
                            ).value = JSON.stringify(
                                confirmResult.paymentIntent
                            );

                            document.getElementById('server-response').submit();
                        }
                    }
                });
        });
    }

    handle() {
        this.init().createPaymentRequest().createPaymentRequestButton();

        this.paymentRequest.canMakePayment().then((result) => {
            if (result) {
                return this.paymentRequestButton.mount(
                    '#payment-request-button'
                );
            }

            document.querySelector('#errors').innerHTML = JSON.parse(
                document.querySelector('meta[name=no-available-methods]')
                    ?.content
            );

            document.querySelector('#errors').hidden = false;
        });

        this.handlePaymentRequestEvents(this.stripe, this.clientSecret);
    }
}

function boot() {
    new StripeBrowserPay().handle()
}

instant() ? boot() : wait('#stripe-browserpay-payment').then(() => boot())
