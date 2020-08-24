/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class ProcessAlipay {
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
            type: 'alipay',
            amount: document.querySelector('meta[name="amount"]').content,
            currency: document.querySelector('meta[name="currency"]').content,
            redirect: {
                return_url: document.querySelector('meta[name="return-url"]')
                    .content,
            },
        };

        document.getElementById('pay-now').addEventListener('submit', (e) => {
            e.preventDefault();
            processingOverlay(true);
            document.getElementById('pay-now').disabled = true;

            this.stripe.createSource(data).then(function(result) {
                if (result.hasOwnProperty('source')) {
                    return (window.location = result.source.redirect.url);
                }

                processingOverlay(false);
                document.getElementById('pay-now').disabled = false;

                this.errors.textContent = '';
                this.errors.textContent = result.error.message;
                this.errors.hidden = false;
            });
        });
    };
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
).content;

new ProcessAlipay(publishableKey).setupStripe().handle();
