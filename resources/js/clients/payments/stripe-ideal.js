/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

import { wait, instant } from '../wait';    

class ProcessIDEALPay {
    constructor(key, stripeConnect) {
        this.key = key;
        this.errors = document.getElementById('errors');
        this.stripeConnect = stripeConnect;
    }

    setupStripe = () => {


        if (this.stripeConnect) {

            this.stripe = Stripe(this.key, {
                stripeAccount: this.stripeConnect,
            });

        }
        else {
            this.stripe = Stripe(this.key);
        }

        var options = {
            style: {
                base: {
                    padding: '10px 12px',
                    color: '#32325d',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    },
                },
            },
            clientSecret: document.querySelector('meta[name=pi-client-secret').content,
        };

        this.elements = this.stripe.elements(options);


        const paymentElementOptions = { layout: 'accordion' };
        this.ideal = this.elements.create('payment', paymentElementOptions);
        this.ideal.mount('#payment-element');

        return this;
    };

    handle = () => {
        document.getElementById('pay-now').addEventListener('click', async (e) => {
            e.preventDefault();
            let errors = document.getElementById('errors');
            errors.textContent = '';
            errors.hidden = true;

            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.remove('hidden');
            document.querySelector('#pay-now > span').classList.add('hidden');

            const { error } = await this.stripe.confirmPayment({
                elements: this.elements,
                confirmParams: {
                    return_url: document.querySelector(
                                'meta[name="return-url"]'
                            ).content,
                },
            });

            if (error) {
                errors.textContent = error.message;
                errors.hidden = false;

                document.getElementById('pay-now').disabled = false;
                document.querySelector('#pay-now > svg').classList.add('hidden');
                document.querySelector('#pay-now > span').classList.remove('hidden');
            } 

        });
    };
}

function boot() {
    const publishableKey = document.querySelector(
        'meta[name="stripe-publishable-key"]'
    )?.content ?? '';
    
    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';
    
    new ProcessIDEALPay(publishableKey, stripeConnect).setupStripe().handle();
}

instant() ? boot() : wait('#stripe-ideal-payment').then(() => boot());
