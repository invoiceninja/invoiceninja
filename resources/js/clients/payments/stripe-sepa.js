/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class ProcessSEPA {
    constructor(key, stripeConnect) {
        this.key = key;
        this.errors = document.getElementById('errors');
        this.stripeConnect = stripeConnect;
    }

    setupStripe = () => {
        this.stripe = Stripe(this.key);

        if(this.stripeConnect)
            this.stripe.stripeAccount = stripeConnect;
        const elements = this.stripe.elements();
        var style = {
            base: {
                color: "#32325d",
                fontFamily:
                    '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                fontSmoothing: "antialiased",
                fontSize: "16px",
                "::placeholder": {
                    color: "#aab7c4"
                },
                ":-webkit-autofill": {
                    color: "#32325d"
                }
            },
            invalid: {
                color: "#fa755a",
                iconColor: "#fa755a",
                ":-webkit-autofill": {
                    color: "#fa755a"
                }
            }
        };
        var options = {
            style: style,
            supportedCountries: ["SEPA"],
            // If you know the country of the customer, you can optionally pass it to
            // the Element as placeholderCountry. The example IBAN that is being used
            // as placeholder reflects the IBAN format of that country.
            placeholderCountry: document.querySelector('meta[name="country"]').content
        };
        this.iban = elements.create("iban", options);
        this.iban.mount("#sepa-iban");
        return this;
    };

    handle = () => {
        document.getElementById('pay-now').addEventListener('click', (e) => {
            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.remove('hidden');
            document.querySelector('#pay-now > span').classList.add('hidden');

            this.stripe.confirmSepaDebitPayment(
                document.querySelector('meta[name=pi-client-secret').content,
                {
                    payment_method: {
                        sepa_debit: this.iban,
                        billing_details: {
                            name: document.getElementById("sepa-name").value,
                            email: document.getElementById("sepa-email-address").value,
                        },
                    },
                }
            ).then((result) => {
                if (result.error) {
                    return this.handleFailure(result.error.message);
                }

                return this.handleSuccess(result);
            });
        });
    };

    handleSuccess(result) {
        document.querySelector(
            'input[name="gateway_response"]'
        ).value = JSON.stringify(result.paymentIntent);

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
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
)?.content ?? '';

const stripeConnect =
    document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

new ProcessSEPA(publishableKey, stripeConnect).setupStripe().handle();
