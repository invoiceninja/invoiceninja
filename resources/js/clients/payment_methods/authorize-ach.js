/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class AuthorizeACH {
    constructor() {
        this.errors = document.getElementById('errors');
        this.key = document.querySelector(
            'meta[name="stripe-publishable-key"]'
        ).content;
    }

    setupStripe = () => {
        this.stripe = Stripe(this.key);

        return this;
    };

    getFormData = () => {
        return {
            country: document.getElementById('country').value,
            currency: document.getElementById('currency').value,
            routing_number: document.getElementById('routing-number').value,
            account_number: document.getElementById('account-number').value,
            account_holder_name: document.getElementById('account-holder-name')
                .value,
            account_holder_type: document.querySelector(
                'input[name="account-holder-type"]:checked'
            ).value,
        };
    };

    handleError = (message) => {
        processingOverlay(false);
        document.getElementById('save-button').disabled = false;

        this.errors.textContent = '';
        this.errors.textContent = message;
        this.errors.hidden = false;
    };

    handleSuccess = (response) => {
        document.getElementById('gateway_response').value = JSON.stringify(
            response
        );

        document.getElementById('server_response').submit();
    };

    handleSubmit = (e) => {
        processingOverlay(true);
        document.getElementById('save-button').disabled = true;

        e.preventDefault();
        this.errors.textContent = '';
        this.errors.hidden = true;

        this.stripe
            .createToken('bank_account', this.getFormData())
            .then((result) => {
                if (result.hasOwnProperty('error')) {
                    return this.handleError(result.error.message);
                }

                return this.handleSuccess(result);
            });
    };

    handle() {
        document
            .getElementById('token-form')
            .addEventListener('submit', (e) => this.handleSubmit(e));
    }
}

new AuthorizeACH().setupStripe().handle();
