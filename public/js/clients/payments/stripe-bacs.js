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

    handle = () => {
        if (this.onlyAuthorization) {
            document.getElementById('authorize-bacs').addEventListener('click', (e) => {
                document.getElementById('authorize-bacs').disabled = true;
                document.querySelector('#authorize-bacs > svg').classList.remove('hidden');
                document.querySelector('#authorize-bacs > span').classList.add('hidden');
                location.href=document.querySelector('meta[name=stripe-redirect-url]').content;
            });}
        else{
            document.getElementById('pay-now').addEventListener('click', (e) => {
                let token = document.querySelector('input[name=token]').value;
                let payNowButton = document.getElementById('pay-now');
                this.payNowButton = payNowButton;
                this.payNowButton.disabled = true;
                this.payNowButton.querySelector('svg').classList.remove('hidden');
                this.payNowButton.querySelector('span').classList.add('hidden');
                this.stripe.confirmBacsDebitPayment(
                    document.querySelector('meta[name=pi-client-secret]').content, {
                        payment_method: token}).then((result) => {
                    if (result.error) {
                        return this.handleFailure(result.error.message);
                    }

                    return this.handleSuccess(result);
                });
            });
        }
    };

    handleFailure(message) {
        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = message;
        errors.hidden = false;

        document.getElementById('pay-now').disabled = false;
        document.querySelector('#pay-now > svg').classList.add('hidden');
        document.querySelector('#pay-now > span').classList.remove('hidden');
    }
    handleSuccess(result) {
        document.querySelector(
            'input[name="gateway_response"]'
        ).value = JSON.stringify(result.paymentIntent);

        document.getElementById('server-response').submit();
    }
}

const publishableKey = document.querySelector(
    'meta[name="stripe-publishable-key"]'
)?.content ?? '';

const stripeConnect =
    document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';
const onlyAuthorization =
    document.querySelector('meta[name="only-authorization"]')?.content ?? '';

new ProcessBACS(publishableKey, stripeConnect).setupStripe().handle();
