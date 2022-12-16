/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

class ProcessBACS {
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

    handle = () => {
        document.getElementById('authorize-bacs').addEventListener('click', (e) => {
            let errors = document.getElementById('errors');

            document.getElementById('authorize-bacs').disabled = true;
            document.querySelector('#authorize-bacs > svg').classList.remove('hidden');
            document.querySelector('#authorize-bacs > span').classList.add('hidden');
            location.href=document.querySelector('meta[name=stripe-redirect-url').content;
        });
    };
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
)?.content ?? '';

const stripeConnect =
    document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

new ProcessBACS(publishableKey, stripeConnect).setupStripe().handle();
