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

wait('#stripe-ach-payment').then(() => ach());

function ach() {
    let payNow = document.getElementById('pay-now');
    const errors = document.getElementById('errors');

    if (payNow) {
        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document.querySelector('input[name=source]').value =
                    element.target.dataset.token;

                const mandateAuthorization = document.getElementById(
                    'mandate-authorization'
                );

                if (mandateAuthorization) {
                    mandateAuthorization.hidden =
                        element.target.dataset.state !== 'inactive';
                }
            })
        );
        payNow.addEventListener('click', function (event) {
            const selectedToken = document.querySelector(
                'input[name="payment-type"]:checked:not(:disabled)'
            );

            if (!selectedToken) {
                event.preventDefault();
                return;
            }

            if (selectedToken?.dataset.state === 'inactive') {
                event.preventDefault();
                renewMandate(selectedToken, payNow);
                return;
            }

            setButtonLoading(payNow, true);
            document.getElementById('server-response').submit();
        });
    }

    const first = document.querySelector(
        'input[name="payment-type"]:not(:disabled)'
    );

    if (first) {
        first.click();
    } else if (payNow) {
        payNow.disabled = true;
    }

    const newBank = document.getElementById('new-bank');

    if (!newBank) {
        return;
    }

    newBank.addEventListener('click', (ev) => {
        if (!document.getElementById('accept-terms').checked) {
            errors.textContent =
                'You must accept the mandate terms prior to making payment.';
            errors.hidden = false;
            return;
        }

        ev.preventDefault();

        errors.hidden = true;

        const accountHolderNameField = document.getElementById(
            'account-holder-name-field'
        );
        const emailField = document.getElementById('email-field');
        const clientSecret = document.querySelector(
            'meta[name="client_secret"]'
        )?.content;
        const address = billingAddress();

        if (!address) {
            errors.textContent =
                'A complete billing address is required to pay by bank account.';
            errors.hidden = false;
            return;
        }

        let newBankButton = document.getElementById('new-bank');
        setButtonLoading(newBankButton, true);

        let stripe;

        try {
            stripe = stripeClient();
        } catch (error) {
            showError(error.message || 'An unexpected error occurred.');
            resetButtons();
            return;
        }

        // Calling this method will open the instant verification dialog.
        stripe
            .collectBankAccountForPayment({
                clientSecret: clientSecret,
                params: {
                    payment_method_type: 'us_bank_account',
                    payment_method_data: {
                        billing_details: {
                            name: accountHolderNameField.value,
                            email: emailField.value,
                            address,
                        },
                    },
                },
                expand: ['payment_method'],
            })
            .then(({ paymentIntent, error }) => {
                if (error) {
                    console.error(error.message);
                    showError(error.message);
                    resetButtons();
                    return;
                }

                if (!paymentIntent) {
                    showError('An unexpected error occurred.');
                    resetButtons();
                    return;
                }

                if (paymentIntent.status === 'requires_payment_method') {
                    // Customer canceled the hosted verification modal. Present them with other
                    // payment method type options.

                    showError(
                        'We were unable to process the payment with this account, please try another one.'
                    );
                    resetButtons();
                    return;
                }

                if (paymentIntent.status === 'requires_confirmation') {
                    let bank_account_response = document.getElementById(
                        'bank_account_response'
                    );
                    bank_account_response.value = JSON.stringify(paymentIntent);

                    return confirmPayment(stripe, clientSecret);
                }

                showError('We were unable to process this payment.');
                resetButtons();
            })
            .catch((error) => {
                showError(error.message || 'An unexpected error occurred.');
                resetButtons();
            });
    });

    function renewMandate(selectedToken, payNowButton) {
        const acceptance = document.getElementById('accept-mandate');

        if (!acceptance?.checked) {
            errors.textContent =
                'You must accept the mandate terms prior to making payment.';
            errors.hidden = false;
            return;
        }

        const clientSecret = document.querySelector(
            'meta[name="mandate_client_secret"]'
        )?.content;

        if (!clientSecret) {
            errors.textContent =
                'We were unable to renew the bank account authorization.';
            errors.hidden = false;
            return;
        }

        errors.hidden = true;
        setButtonLoading(payNowButton, true);

        let stripe;

        try {
            stripe = stripeClient();
        } catch (error) {
            showError(error.message || 'An unexpected error occurred.');
            setButtonLoading(payNowButton, false);
            return;
        }

        stripe
            .confirmUsBankAccountSetup(clientSecret, {
                payment_method: selectedToken.dataset.paymentMethod,
            })
            .then(({ setupIntent, error }) => {
                if (error || setupIntent?.status !== 'succeeded') {
                    errors.textContent =
                        error?.message ||
                        'We were unable to renew the bank account authorization.';
                    errors.hidden = false;
                    setButtonLoading(payNowButton, false);
                    return;
                }

                document.getElementById('setup_intent_id').value =
                    setupIntent.id;
                document.getElementById('server-response').submit();
            })
            .catch((error) => {
                showError(error.message || 'An unexpected error occurred.');
                setButtonLoading(payNowButton, false);
            });
    }

    function stripeClient() {
        const publishableKey = document.querySelector(
            'meta[name="stripe-publishable-key"]'
        ).content;
        const stripeConnect = document.querySelector(
            'meta[name="stripe-account-id"]'
        )?.content;

        return stripeConnect
            ? Stripe(publishableKey, { stripeAccount: stripeConnect })
            : Stripe(publishableKey);
    }

    function confirmPayment(stripe, clientSecret) {
        return stripe
            .confirmUsBankAccountPayment(clientSecret)
            .then(({ paymentIntent, error }) => {
                console.log(paymentIntent);
                if (error) {
                    console.error(error.message);
                    showError(error.message);
                    resetButtons();
                    return;
                }

                if (!paymentIntent) {
                    showError('An unexpected error occurred.');
                    resetButtons();
                    return;
                }

                if (paymentIntent.status === 'requires_payment_method') {
                    // Confirmation failed. Attempt again with a different payment method.

                    showError(
                        'We were unable to process the payment with this account, please try another one.'
                    );
                    resetButtons();
                    return;
                }

                if (paymentIntent.status === 'processing') {
                    // Confirmation succeeded! The account will be debited.

                    let gateway_response =
                        document.getElementById('gateway_response');
                    gateway_response.value = JSON.stringify(paymentIntent);
                    document.getElementById('server-response').submit();
                    return;
                }

                if (
                    paymentIntent.next_action?.type ===
                        'verify_with_microdeposits' ||
                    paymentIntent.next_action?.type === 'requires_source_action'
                ) {
                    errors.textContent =
                        'You will receive an email with details on how to verify your bank account and process payment.';
                    errors.hidden = false;
                    document.getElementById('new-bank').style.visibility =
                        'hidden';

                    let gateway_response =
                        document.getElementById('gateway_response');
                    gateway_response.value = JSON.stringify(paymentIntent);
                    document.getElementById('server-response').submit();
                    return;
                }

                showError('We were unable to process this payment.');
                resetButtons();
            })
            .catch((error) => {
                showError(error.message || 'An unexpected error occurred.');
                resetButtons();
            });
    }

    function showError(message) {
        errors.textContent = message;
        errors.hidden = false;
    }

    /**
     * Nacha requires a complete billing address on the bank account payment method.
     * Line 2 is optional; every other address field must be present.
     */
    function billingAddress() {
        const meta = (name) =>
            document.querySelector(`meta[name="${name}"]`)?.content.trim() ||
            '';

        const address = {
            line1: meta('address-1'),
            line2: meta('address-2'),
            city: meta('city'),
            state: meta('state'),
            postal_code: meta('postal_code'),
            country: meta('country').toUpperCase(),
        };

        const requiredFields = [
            'line1',
            'city',
            'state',
            'postal_code',
            'country',
        ];

        if (
            requiredFields.some((field) => address[field].length === 0) ||
            address.country.length !== 2
        ) {
            return null;
        }

        return Object.fromEntries(
            Object.entries(address).filter(([, value]) => value.length > 0)
        );
    }

    function resetButtons() {
        let newBankButton = document.getElementById('new-bank');
        setButtonLoading(newBankButton, false);
    }

    function setButtonLoading(button, loading) {
        button.disabled = loading;
        button.querySelector('svg').classList.toggle('hidden', !loading);
        button.querySelector('span').classList.toggle('hidden', loading);
    }
}
