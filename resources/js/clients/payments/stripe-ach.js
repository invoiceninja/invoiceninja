/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

class AuthorizeACH {
    constructor() {
        this.errors = document.getElementById('errors');
        this.key = document.querySelector(
            'meta[name="stripe-publishable-key"]'
        ).content;
        this.stripe_connect = document.querySelector(
            'meta[name="stripe-account-id"]'
        )?.content;
        this.clientSecret = document.querySelector(
            'meta[name="stripe-client-secret"]'
        )?.content;
    }

    setupStripe = () => {
        if (this.stripe_connect) {
            this.stripe = Stripe(this.key, {
                stripeAccount: this.stripe_connect,
            });
        } else {
            this.stripe = Stripe(this.key);
        }

        return this;
    };

    getFormData = () => {
        return {
            account_holder_name: document.getElementById('account-holder-name').value,
            account_holder_type: document.querySelector(
                'input[name="account-holder-type"]:checked'
            ).value,
            email: document.querySelector('meta[name="contact-email"]')?.content || '',
        };
    };

    handleError = (message) => {
        document.getElementById('save-button').disabled = false;
        document.querySelector('#save-button > svg').classList.add('hidden');
        document.querySelector('#save-button > span').classList.remove('hidden');

        this.errors.textContent = '';
        this.errors.textContent = message;
        this.errors.hidden = false;
    };

    handleSuccess = (setupIntent) => {
        document.getElementById('gateway_response').value = JSON.stringify(setupIntent);
        document.getElementById('server_response').submit();
    };

    handleSubmit = async (e) => {
        e.preventDefault();

        if (!document.getElementById('accept-terms').checked) {
            this.errors.textContent = "You must accept the mandate terms prior to adding this payment method.";
            this.errors.hidden = false;
            return;
        }

        document.getElementById('save-button').disabled = true;
        document.querySelector('#save-button > svg').classList.remove('hidden');
        document.querySelector('#save-button > span').classList.add('hidden');

        this.errors.textContent = '';
        this.errors.hidden = true;

        const formData = this.getFormData();

        try {
            // Step 1: Collect bank account using Financial Connections
            const { setupIntent, error } = await this.stripe.collectBankAccountForSetup({
                clientSecret: this.clientSecret,
                params: {
                    payment_method_type: 'us_bank_account',
                    payment_method_data: {
                        billing_details: {
                            name: formData.account_holder_name,
                            email: formData.email,
                        },
                    },
                },
            });

            if (error) {
                return this.handleError(error.message);
            }

            // Check the SetupIntent status
            if (setupIntent.status === 'requires_payment_method') {
                // Customer closed the modal without completing - show error
                return this.handleError('Please complete the bank account verification process.');
            }

            if (setupIntent.status === 'requires_confirmation') {
                // User completed Financial Connections, now confirm the SetupIntent
                const { setupIntent: confirmedSetupIntent, error: confirmError } = 
                    await this.stripe.confirmUsBankAccountSetup(this.clientSecret);

                if (confirmError) {
                    return this.handleError(confirmError.message);
                }

                return this.handleSuccess(confirmedSetupIntent);
            }

            if (setupIntent.status === 'requires_action') {
                // Microdeposit verification required - redirect to verification
                return this.handleSuccess(setupIntent);
            }

            if (setupIntent.status === 'succeeded') {
                // Instant verification succeeded
                return this.handleSuccess(setupIntent);
            }

            // Handle any other status
            return this.handleSuccess(setupIntent);

        } catch (err) {
            return this.handleError(err.message || 'An unexpected error occurred.');
        }
    };

    handle() {
        document
            .getElementById('save-button')
            .addEventListener('click', (e) => this.handleSubmit(e));
    }
}

new AuthorizeACH().setupStripe().handle();
