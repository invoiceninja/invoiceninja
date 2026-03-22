/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

class AuthorizeAuthorizeACH {
    constructor(publicKey, loginId) {
        this.form = document.getElementById('server_response');
        this.submitButton = document.getElementById('pay-now');
        this.errorDiv = document.getElementById('errors');
        this.publicKey = publicKey;
        this.loginId = loginId;
        // Input fields
        this.accountHolderName = document.getElementById('account_holder_name');
        this.routingNumber = document.getElementById('routing_number');
        this.accountNumber = document.getElementById('account_number');
        this.acceptTerms = document.getElementById('accept-terms');
        // Validation state
        this.isValid = {
            accountHolderName: false,
            routingNumber: false,
            accountNumber: false,
            acceptTerms: false
        };

        this.setupEventListeners();
        this.updateSubmitButton();
    }

    handleAuthorization = () => {
       
        if (!this.isFormValid()) {
            return;
        }

        // Disable the submit button and show loading state
        this.submitButton.disabled = true;
        this.submitButton.querySelector('svg')?.classList.remove('hidden');
        this.submitButton.querySelector('span')?.classList.add('hidden');

        // Get the selected account type
        const accountType = document.querySelector('input[name="account_type"]:checked').value ?? 'checking';

        var authData = {};
        authData.clientKey = this.publicKey;
        authData.apiLoginID = this.loginId;

        // Prepare the data for Authorize.net
        var bankData = {};
        bankData.accountType = accountType;
        bankData.routingNumber = this.routingNumber.value;
        bankData.accountNumber = this.accountNumber.value;
        bankData.nameOnAccount = this.accountHolderName.value;

        var secureData = {};
        secureData.authData = authData;
        secureData.bankData = bankData;

        Accept.dispatchData(secureData, this.responseHandler);

        return false;
    };


    setupEventListeners() {
        // Monitor account holder name
        this.accountHolderName.addEventListener('input', () => {
            this.validateAccountHolderName();
            this.updateSubmitButton();
        });

        // Monitor routing number
        this.routingNumber.addEventListener('input', () => {
            this.validateRoutingNumber();
            this.updateSubmitButton();
        });

        // Monitor account number
        this.accountNumber.addEventListener('input', () => {
            this.validateAccountNumber();
            this.updateSubmitButton();
        });

        // Submit button handler
        this.submitButton.addEventListener('click', (e) => {
            e.preventDefault();
            if (this.isFormValid()) {
                this.handleAuthorization();
            }
        });

        this.acceptTerms.addEventListener('change', () => {
            this.validateAcceptTerms();
            this.updateSubmitButton();
        });
    }


    validateAcceptTerms() {
        this.isValid.acceptTerms = this.acceptTerms.checked;
        if (!this.isValid.acceptTerms) {
            this.acceptTerms.classList.add('border-red-500');
        } else {
            this.acceptTerms.classList.remove('border-red-500');
        }
    }


    validateAccountHolderName() {
        const name = this.accountHolderName.value.trim();
        this.isValid.accountHolderName = name.length > 0 && name.length <= 22;

        if (!this.isValid.accountHolderName) {
            this.accountHolderName.classList.add('border-red-500');
        } else {
            this.accountHolderName.classList.remove('border-red-500');
        }
    }

    validateRoutingNumber() {
        const routing = this.routingNumber.value.replace(/\D/g, '');
        this.isValid.routingNumber = routing.length === 9;

        if (!this.isValid.routingNumber) {
            this.routingNumber.classList.add('border-red-500');
        } else {
            this.routingNumber.classList.remove('border-red-500');
        }
    }

    validateAccountNumber() {
        const account = this.accountNumber.value.replace(/\D/g, '');
        this.isValid.accountNumber = account.length >= 1 && account.length <= 17;

        if (!this.isValid.accountNumber) {
            this.accountNumber.classList.add('border-red-500');
        } else {
            this.accountNumber.classList.remove('border-red-500');
        }
    }

    isFormValid() {
        return Object.values(this.isValid).every(Boolean);
    }

    updateSubmitButton() {
        const isValid = this.isFormValid();
        this.submitButton.disabled = !isValid;

        // Visual feedback
        if (isValid) {
            this.submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            this.submitButton.classList.add('hover:bg-primary-dark');
        } else {
            this.submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            this.submitButton.classList.remove('hover:bg-primary-dark');
        }
    }

    handlePayNowAction(token_hashed_id) {
        document.getElementById('pay-now').disabled = true;
        document.querySelector('#pay-now > svg').classList.remove('hidden');
        document.querySelector('#pay-now > span').classList.add('hidden');

        document.getElementById('token').value = token_hashed_id;
        document.getElementById('server_response').submit();
    }

    responseHandler = (response) => {
        if (response.messages.resultCode === 'Error') {
            var i = 0;

            const $errors = document.getElementById('errors'); // get the reference of the div

            if ($errors) {
                $errors.innerText = `${response.messages.message[i].code}: ${response.messages.message[i].text}`;
                $errors.style.display = 'block';
            }

            document.getElementById('pay-now').disabled = false;
            document.querySelector('#pay-now > svg').classList.add('hidden');
            document
                .querySelector('#pay-now > span')
                .classList.remove('hidden');
        } else if (response.messages.resultCode === 'Ok') {
            document.getElementById('dataDescriptor').value =
                response.opaqueData.dataDescriptor;
            document.getElementById('dataValue').value =
                response.opaqueData.dataValue;

            let storeCard = document.querySelector(
                'input[name=token-billing-checkbox]:checked'
            );

            if (storeCard) {
                document.getElementById('store_card').value = storeCard.value;
            }

            document.getElementById('server_response').submit();
        }

        return false;
    };

    handle = () => {
        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (e) => {
                document.getElementById(
                    'authorize-ach-container'
                ).style.display = 'none';

                document.getElementById('token').value = e.target.dataset.token;

                document.getElementById('pay-now').disabled = false;
                document.querySelector('#pay-now > svg').classList.add('hidden');
                document
                    .querySelector('#pay-now > span')
                    .classList.remove('hidden');
            })
        );

        let payWithACHToggle = document.getElementById(
            'toggle-payment-with-ach'
        );

        if (payWithACHToggle) {
            payWithACHToggle.addEventListener('click', () => {
                document.getElementById(
                    'authorize-ach-container'
                ).style.display = 'grid';

                document.getElementById('token').value = null;

                this.updateSubmitButton();
            });
        }

        let payNowButton = document.getElementById('pay-now');

        if (payNowButton) {
            payNowButton.addEventListener('click', (e) => {
                let token = document.getElementById('token');

                token.value
                    ? this.handlePayNowAction(token.value)
                    : this.handleAuthorization();
            });
        }

        return this;
    };
}

function boot() {
    const publicKey = document.querySelector(
        'meta[name="authorize-public-key"]'
    ).content;

    const loginId = document.querySelector(
        'meta[name="authorize-login-id"]'
    ).content;

    /** @handle */
    new AuthorizeAuthorizeACH(publicKey, loginId).handle();

    /** @type {NodeListOf<HTMLInputElement>} */
    const tokens = document.querySelectorAll('input.toggle-payment-with-token');

    if (tokens.length > 0) {
        tokens[0].click();
    }
}

instant() ? boot() : wait('#authorize-net-ach-payment').then(() => boot());
