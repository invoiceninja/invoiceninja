/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class ProcessAlipay {
    constructor(key, stripeConnect) {
        this.key = key;
        this.stripeConnect = stripeConnect;

        this.errors = document.getElementById('errors');
    }

    setupStripe = () => {
        this.stripe = Stripe(this.key);

        if(this.stripeConnect)
            this.stripe.stripeAccount = this.stripeConnect;
        
        return this;
    };

    handle = () => {
        let data = {
            type: 'alipay',
            amount: document.querySelector('meta[name="amount"]').content,
            currency: document.querySelector('meta[name="currency"]').content,
            redirect: {
                return_url: document.querySelector('meta[name="return-url"]')
                    .content,
            },
        };

        document.getElementById('pay-now').addEventListener('click', (e) => {
            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.add('hidden');
            document.querySelector('#pay-now > span').classList.remove('hidden');

            this.stripe.createSource(data).then(function(result) {
                if (result.hasOwnProperty('source')) {
                    return (window.location = result.source.redirect.url);
                }

                document.getElementById('pay-now').disabled = false;
                document.querySelector('#pay-now > svg').classList.remove('hidden');
                document.querySelector('#pay-now > span').classList.add('hidden');

                this.errors.textContent = '';
                this.errors.textContent = result.error.message;
                this.errors.hidden = false;
            });
        });
    };
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
)?.content ?? '';

const stripeConnect = document.querySelector(
    'meta[name="stripe-account-id"]'
)?.content ?? '';

new ProcessAlipay(publishableKey, stripeConnect).setupStripe().handle();
