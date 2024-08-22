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

wait('#stripe-acss-authorize').then(() => authorize());

function authorize() {
    let stripe;

    const account_id = document.querySelector(
        'meta[name="stripe-account-id"]'
    )?.content;

    const publishable_key = document.querySelector(
        'meta[name="stripe-publishable-key"]'
    )?.content;

    if (account_id && account_id.length > 0) {
        stripe = Stripe(publishable_key, {
            stripeAccount: account_id,
        });
    } else {
        stripe = Stripe(publishable_key);
    }

    const accountholderName = document.getElementById('acss-name');
    const email = document.getElementById('acss-email-address');
    const submitButton = document.getElementById('authorize-acss');
    const clientSecret = document.querySelector('meta[name="stripe-pi-client-secret"]')?.content;
    const errors = document.getElementById('errors');

    submitButton.addEventListener('click', async (event) => {
        event.preventDefault();
        errors.hidden = true;
        submitButton.disabled = true;

        const validEmailRegex =
            /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;

        if (email.value.length < 3 || !email.value.match(validEmailRegex)) {
            errors.textContent = 'Please enter a valid email address.';
            errors.hidden = false;
            submitButton.disabled = false;
            return;
        }

        if (accountholderName.value.length < 3) {
            errors.textContent = 'Please enter a name for the account holder.';
            errors.hidden = false;
            submitButton.disabled = false;
            return;
        }

        const { setupIntent, error } = await stripe.confirmAcssDebitSetup(
            clientSecret,
            {
                payment_method: {
                    billing_details: {
                        name: accountholderName.value,
                        email: email.value,
                    },
                },
            }
        );

        // Handle next step based on SetupIntent's status.
        document.getElementById('gateway_response').value = JSON.stringify(
            setupIntent ?? error
        );
        document.getElementById('server_response').submit();
    });
}
