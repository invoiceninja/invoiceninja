/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { instant, wait } from '../wait';

class ProcessBACS {
    constructor(key, stripeConnect) {
        this.key = key;
        this.errors = document.getElementById('errors');
        this.stripeConnect = stripeConnect;
        this.onlyAuthorization = onlyAuthorization;
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
    payment_data;

    handle = () => {

        if (this.onlyAuthorization) {
            document.getElementById('authorize-bacs').addEventListener('click', (e) => {
                document.getElementById('authorize-bacs').disabled = true;
                document.querySelector('#authorize-bacs > svg').classList.remove('hidden');
                document.querySelector('#authorize-bacs > span').classList.add('hidden');
                location.href=document.querySelector('meta[name=stripe-redirect-url]').content;
            });}
        else{
            this.payNowButton = document.getElementById('pay-now');
            document.getElementById('pay-now').addEventListener('click', (e) => {
                this.payNowButton.disabled = true;
                this.payNowButton.querySelector('svg').classList.remove('hidden');
                this.payNowButton.querySelector('span').classList.add('hidden');
                document.getElementById('server-response').submit();
            });

            this.payment_data = Array.from(document.getElementsByClassName('toggle-payment-with-token'));
            if (this.payment_data.length > 0){
                this.payment_data.forEach((element) =>
                    element.addEventListener('click', (element) => {
                        document.querySelector('input[name=token]').value =
                            element.target.dataset.token;
                    })
                );}
            else{
                this.errors.textContent = document.querySelector(
                    'meta[name=translation-payment-method-required]'
                ).content;
                this.errors.hidden = false;
                this.payNowButton.disabled = true;
                this.payNowButton.querySelector('span').classList.remove('hidden');
                this.payNowButton.querySelector('svg').classList.add('hidden');
            }}


    }
}

function boot() {
    const publishableKey = document.querySelector(
        'meta[name="stripe-publishable-key"]'
    )?.content ?? '';
    
    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';
    const onlyAuthorization =
        document.querySelector('meta[name="only-authorization"]')?.content ?? '';
    
    new ProcessBACS(publishableKey, stripeConnect).setupStripe().handle();
}

instant() ? boot() : wait('#stripe-bacs-payment').then(() => boot());
