/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait } from '../wait';

wait('#stripe-bank-transfer-payment').then(() => bankTransfer());

function bankTransfer() {
    const secret = document.querySelector('meta[name="stripe-client-secret"]')?.content;
    const return_url = document.querySelector('meta[name="stripe-return-url"]')?.content;

    const options = {
        clientSecret: secret,
        appearance: {
            theme: 'stripe',
            variables: {
                colorPrimary: '#0570de',
                colorBackground: '#ffffff',
                colorText: '#30313d',
                colorDanger: '#df1b41',
                fontFamily: 'Ideal Sans, system-ui, sans-serif',
                spacingUnit: '2px',
                borderRadius: '4px',
                // See all possible variables below
            },
        },
    };

    const stripe = Stripe(
        document
            .querySelector('meta[name="stripe-publishable-key"]')
            .getAttribute('content')
    );
    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

    if (stripeConnect) stripe.stripeAccount = stripeConnect;

    // Set up Stripe.js and Elements to use in checkout form, passing the client secret obtained in step 3
    const elements = stripe.elements(options);
    // Create and mount the Payment Element
    const paymentElement = elements.create('payment');
    paymentElement.mount('#payment-element');

    const form = document.getElementById('payment-form');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        document.getElementById('pay-now').disabled = true;
        document.querySelector('#pay-now > svg').classList.add('hidden');
        document.querySelector('#pay-now > span').classList.remove('hidden');

        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url,
            },
        });

        if (error) {
            document.getElementById('pay-now').disabled = false;
            document.querySelector('svg').classList.remove('hidden');
            document.querySelector('span').classList.add('hidden');
            const messageContainer = document.querySelector('#errors');
            messageContainer.textContent = error.message;
        }
    });
}
