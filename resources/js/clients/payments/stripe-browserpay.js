/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class StripeBrowserPay {
    constructor() {
        this.clientSecret = document.querySelector(
            'meta[name=stripe-pi-client-secret]'
        )?.content;
    }

    init() {
        this.stripe = Stripe(
            document.querySelector('meta[name=stripe-publishable-key]')?.content
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
        this.paymentRequest.on('paymentmethod', function (ev) {
            stripe
                .confirmCardPayment(
                    clientSecret,
                    { payment_method: ev.paymentMethod.id },
                    { handleActions: false }
                )
                .then(function (confirmResult) {
                    console.log('confirmResult', confirmResult);

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
                            confirmResult.paymentIntent.status ===
                            'requires_action'
                        ) {
                            // Let Stripe.js handle the rest of the payment flow.
                            stripe
                                .confirmCardPayment(clientSecret)
                                .then(function (result) {
                                    if (result.error) {
                                        ev.complete('fail');
                                    } else {
                                        // The payment has succeeded.
                                    }
                                });
                        } else {
                            // The payment has succeeded.
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

new StripeBrowserPay().handle();
