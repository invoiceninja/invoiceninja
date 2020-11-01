/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class ProcessSOFORT {
    constructor(key) {
        this.key = key;
        this.errors = document.getElementById('errors');
    }

    setupStripe = () => {
        this.stripe = Stripe(this.key);

        return this;
    };

    handle = () => {
        let data = {
            type: 'sofort',
            amount: document.querySelector('meta[name="amount"]').content,
            currency: 'eur',
            redirect: {
                return_url: document.querySelector('meta[name="return-url"]')
                    .content,
            },
            sofort: {
                country: document.querySelector('meta[name="country"').content,
            },
        };

        document.getElementById('pay-now').addEventListener('click', (e) => {
            document.getElementById('pay-now-button').disabled = true;
            document.querySelector('#pay-now-button > svg').classList.remove('hidden');
            document.querySelector('#pay-now-button > span').classList.add('hidden');

            this.stripe.createSource(data).then(function(result) {
                if (result.hasOwnProperty('source')) {
                    return (window.location = result.source.redirect.url);
                }

                document.getElementById('pay-now-button').disabled = false;
                document.querySelector('#pay-now-button > svg').classList.add('hidden');
                document.querySelector('#pay-now-button > span').classList.remove('hidden');

                this.errors.textContent = '';
                this.errors.textContent = result.error.message;
                this.errors.hidden = false;

                document.getElementById('pay-now').disabled = false;
            });
        });
    };
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
).content;

new ProcessSOFORT(publishableKey).setupStripe().handle();
