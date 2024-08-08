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

class ProcessBECS {
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

        const elements = this.stripe.elements();
        const style = {
            base: {
                color: '#32325d',
                fontSize: '16px',
                '::placeholder': {
                    color: '#aab7c4'
                },
                ':-webkit-autofill': {
                    color: '#32325d',
                },
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a',
                ':-webkit-autofill': {
                    color: '#fa755a',
                },
            }
        };

        const options = {
            style: style,
            disabled: false,
            hideIcon: false,
            iconStyle: "default", // or "solid"
        };
        this.auBankAccount = elements.create("auBankAccount", options);
        this.auBankAccount.mount("#becs-iban");
        return this;
    };

    handle = () => {
        document.getElementById('pay-now').addEventListener('click', (e) => {

        let errors = document.getElementById('errors');

        if (document.getElementById('becs-name').value === "") {
            document.getElementById('becs-name').focus();
            errors.textContent = document.querySelector('meta[name=translation-name-required]').content;
            errors.hidden = false;
            return;
        }

        if (document.getElementById('becs-email-address').value === "") {
            document.getElementById('becs-email-address').focus();
            errors.textContent = document.querySelector('meta[name=translation-email-required]').content;
            errors.hidden = false;
            return ;
        }


        if (!document.getElementById('becs-mandate-acceptance').checked) {
            document.getElementById('becs-mandate-acceptance').focus();
            errors.textContent = document.querySelector('meta[name=translation-terms-required]').content;
            errors.hidden = false;
            console.log("Terms");
            return ;
        }

            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.remove('hidden');
            document.querySelector('#pay-now > span').classList.add('hidden');

            this.stripe.confirmAuBecsDebitPayment(
                document.querySelector('meta[name=pi-client-secret').content,
                {
                    payment_method: {
                        au_becs_debit: this.auBankAccount,
                        billing_details: {
                            name: document.getElementById("becs-name").value,
                            email: document.getElementById("becs-email-address").value,
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

            document.getElementById('pay-now').disabled = false;
            document.querySelector('#pay-now > svg').classList.add('hidden');
            document.querySelector('#pay-now > span').classList.remove('hidden');
    }
}

function boot() {
    const publishableKey = document.querySelector(
        'meta[name="stripe-publishable-key"]'
    )?.content ?? '';
    
    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';
    
    new ProcessBECS(publishableKey, stripeConnect).setupStripe().handle();
}

instant() ? boot() : wait('#stripe-becs-payment').then(() => boot());
