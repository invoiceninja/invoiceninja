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

class ProcessPRZELEWY24 {
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

        
        let elements = this.stripe.elements()
        var options = {
            // Custom styling can be passed to options when creating an Element
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
        };
        this.p24bank = elements.create('p24Bank', options)
        this.p24bank.mount('#p24-bank-element');
        return this;
    };

    handle = () => {
        document.getElementById('pay-now').addEventListener('click', (e) => {
            let errors = document.getElementById('errors');

            if (document.getElementById('p24-name').value === "") {
                document.getElementById('p24-name').focus();
                errors.textContent = document.querySelector('meta[name=translation-name-required]').content;
                errors.hidden = false;
                return;
            }
            if (document.getElementById('p24-email-address').value === "") {
                document.getElementById('p24-email-address').focus();
                errors.textContent = document.querySelector('meta[name=translation-email-required]').content;
                errors.hidden = false;
                return;
            }
            if (!document.getElementById('p24-mandate-acceptance').checked) {
                document.getElementById('p24-mandate-acceptance').focus();
                errors.textContent = document.querySelector('meta[name=translation-terms-required]').content;
                errors.hidden = false;
                return;
            }

            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.remove('hidden');
            document.querySelector('#pay-now > span').classList.add('hidden');

            this.stripe.confirmP24Payment(
                document.querySelector('meta[name=pi-client-secret').content,
                {
                    payment_method: {
                        p24: this.p24bank,
                        billing_details: {
                            name: document.getElementById('p24-name').value,
                            email: document.getElementById('p24-email-address').value,
                        },
                    },
                    payment_method_options: {
                        p24: {
                            tos_shown_and_accepted: document.getElementById('p24-mandate-acceptance').checked,
                        },
                    },
                    return_url: document.querySelector('meta[name="return-url"]').content,
                }
            ).then(function (result) {

                if (result.error) {
                    // Show error to your customer
                    errors.textContent = result.error.message;
                    errors.hidden = false;
                    document.getElementById('pay-now').disabled = false;
                    document.querySelector('#pay-now > svg').classList.add('hidden');
                    document.querySelector('#pay-now > span').classList.remove('hidden');
                } else {
                    // The payment has been processed!
                    if (result.paymentIntent.status === 'succeeded') {
                        window.location = document.querySelector('meta[name="return-url"]').content;
                    }
                }
            });
        });
    };
}

function boot() {
    const publishableKey = document.querySelector(
        'meta[name="stripe-publishable-key"]'
    )?.content ?? '';
    
    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';
    
    new ProcessPRZELEWY24(publishableKey, stripeConnect).setupStripe().handle();
}

instant() ? boot() : wait('#stripe-przelewy24-payment').then(() => boot());
