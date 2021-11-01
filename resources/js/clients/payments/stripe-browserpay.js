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
        document.querySelector('#errors').hidden = true;

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
                        ev.complete('fail');

                        document.querySelector('#errors').innerText =
                            confirmResult.error.message;

                        document.querySelector('#errors').hidden = false;
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

                                        let tokenBillingCheckbox = document.querySelector(
                                            'input[name="token-billing-checkbox"]:checked'
                                        );
                                
                                        if (tokenBillingCheckbox) {
                                            document.querySelector('input[name="store_card"]').value =
                                                tokenBillingCheckbox.value;
                                        }

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

                            let tokenBillingCheckbox = document.querySelector(
                                'input[name="token-billing-checkbox"]:checked'
                            );
                    
                            if (tokenBillingCheckbox) {
                                document.querySelector('input[name="store_card"]').value =
                                    tokenBillingCheckbox.value;
                            }

                            document.getElementById('server-response').submit();
                        }
                    }
                });
        });
    }

    handleSuccess(result) {
        document.querySelector('input[name="gateway_response"]').value =
            JSON.stringify(result.paymentIntent);

        let tokenBillingCheckbox = document.querySelector(
            'input[name="token-billing-checkbox"]:checked'
        );

        if (tokenBillingCheckbox) {
            document.querySelector('input[name="store_card"]').value =
                tokenBillingCheckbox.value;
        }

        document.getElementById('server-response').submit();
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
