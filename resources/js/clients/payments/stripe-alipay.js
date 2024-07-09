/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

class ProcessAlipay {
    constructor(key, stripeConnect) {
        this.key = key;
        this.stripeConnect = stripeConnect;

        this.errors = document.getElementById('errors');
    }

    setupStripe = () => {
        if (this.stripeConnect) {
            // this.stripe.stripeAccount = this.stripeConnect;

            this.stripe = Stripe(this.key, {
                stripeAccount: this.stripeConnect,
            });
        } else {
            this.stripe = Stripe(this.key);
        }

        return this;
    };

    async handle() {
        document
            .getElementById('pay-now')
            .addEventListener('click', async (e) => {
                document.getElementById('pay-now').disabled = true;
                document
                    .querySelector('#pay-now > svg')
                    .classList.add('hidden');
                document
                    .querySelector('#pay-now > span')
                    .classList.remove('hidden');

                const { error } = await this.stripe.confirmAlipayPayment(
                    document.querySelector('meta[name=ci_intent]').content,
                    {
                        // Return URL where the customer should be redirected after the authorization
                        return_url: `${
                            document.querySelector('meta[name=return_url]')
                                .content
                        }`,
                    }
                );

                document.getElementById('pay-now').disabled = false;
                document
                    .querySelector('#pay-now > svg')
                    .classList.remove('hidden');
                document
                    .querySelector('#pay-now > span')
                    .classList.add('hidden');

                if (error) {
                    this.errors.textContent = '';
                    this.errors.textContent = result.error.message;
                    this.errors.hidden = false;
                }
            });
    }
}

wait('#stripe-alipay-payment').then(() => {
    const publishableKey =
        document.querySelector('meta[name="stripe-publishable-key"]')
            ?.content ?? '';

    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

    new ProcessAlipay(publishableKey, stripeConnect).setupStripe().handle();
});
