/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

class ProcessKlarna {
    constructor(key, stripeConnect) {
        this.key = key;
        this.errors = document.getElementById('errors');
        this.stripeConnect = stripeConnect;
    }

    setupStripe = () => {

        if (this.stripeConnect){
            // this.stripe.stripeAccount = this.stripeConnect;

            this.stripe = Stripe(this.key, {
                stripeAccount: this.stripeConnect,
            });

        }
        else {
            this.stripe = Stripe(this.key);
        }


        return this;
    };

    handleError = (message) => {
        document.getElementById('pay-now').disabled = false;
        document.querySelector('#pay-now > svg').classList.add('hidden');
        document.querySelector('#pay-now > span').classList.remove('hidden');

        this.errors.textContent = '';
        this.errors.textContent = message;
        this.errors.hidden = false;
    };

    handle = () => {
        document.getElementById('pay-now').addEventListener('click', (e) => {
            let errors = document.getElementById('errors');
            let name = document.getElementById("klarna-name").value;
            if (! /^[A-Za-z\s]*$/.test(name)){
                document.getElementById('klarna-name-correction').hidden = false;
                document.getElementById('klarna-name').textContent = name.replace(/^[A-Za-z\s]*$/, "")
                document.getElementById('klarna-name').focus();
                errors.textContent = document.querySelector(
                    'meta[name=translation-name-without-special-characters]'
                ).content;
                errors.hidden = false;
            }
            else {
                document.getElementById('pay-now').disabled = true;
                document.querySelector('#pay-now > svg').classList.remove('hidden');
                document.querySelector('#pay-now > span').classList.add('hidden');
                this.stripe.confirmKlarnaPayment(
                    document.querySelector('meta[name=pi-client-secret').content,
                    {
                        payment_method: {
                            billing_details: {
                                name: name,
                                email: document.querySelector('meta[name=email]').content,
                                address: {
                                    line1: document.querySelector('meta[name=address-1]').content,
                                    line2: document.querySelector('meta[name=address-2]').content,
                                    city: document.querySelector('meta[name=city]').content,
                                    postal_code: document.querySelector('meta[name=postal_code]').content,
                                    state: document.querySelector('meta[name=state]').content,
                                    country: document.querySelector('meta[name=country]').content,
                                }
                            },
                        },
                        return_url: document.querySelector(
                            'meta[name="return-url"]'
                        ).content,
                    }
                    ).then((result) => {
                    if (result.hasOwnProperty('error')) {
                        return this.handleError(result.error.message);
                    }

                });}
        });
    };
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
)?.content ?? '';

const stripeConnect =
    document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

new ProcessKlarna(publishableKey, stripeConnect).setupStripe().handle();
