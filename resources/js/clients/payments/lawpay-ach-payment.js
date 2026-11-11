/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

import { wait, instant } from '../wait';

class LawPayACH {
    constructor(publicKey) {
        this.publicKey = publicKey;
        this.hostedFieldsInstance = null;

        this.initializeHostedFields();
    }

    initializeHostedFields() {
        this.hostedFieldsInstance = window.AffiniPay.hostedFields.initializeFields(
            {
                publicKey: this.publicKey,
                fields: [
                    {
                        selector: '#lawpay_routing_number',
                        input: {
                            type: 'routing_number',
                            css: {
                                'font-size': '14px',
                                'font-family': 'inherit',
                            },
                        },
                    },
                    {
                        selector: '#lawpay_account_number',
                        input: {
                            type: 'bank_account_number',
                            css: {
                                'font-size': '14px',
                                'font-family': 'inherit',
                            },
                        },
                    },
                ],
            },
            function (state) {
                // Field validation state callback
            }
        );
    }

    handleAuthorization = () => {
        document.getElementById('lawpay_errors').innerHTML = '';

        const accountHolderName = document.getElementById('account-holder-name');

        if (!accountHolderName || !accountHolderName.value.trim()) {
            this.showError('Account holder name is required.');
            return;
        }

        let payNowButton = document.getElementById('pay-now');
        if (payNowButton) {
            payNowButton.disabled = true;
            payNowButton.querySelector('svg').classList.remove('hidden');
            payNowButton.querySelector('span').classList.add('hidden');
        }

        window.AffiniPay.hostedFields
            .getPaymentToken({
                account_holder_name: accountHolderName.value,
                account_holder_type: 'individual',
                account_type: 'checking',
            })
            .then(this.successResponseHandler)
            .catch(this.failedResponseHandler);

        return false;
    };

    successResponseHandler = (response) => {
        document.getElementById('payment_token').value = response.id;
        document.getElementById('last_4').value =
            response.last_four || '';

        let accountHolderName = document.getElementById('account-holder-name');
        if (accountHolderName) {
            document.getElementById('account_holder_name').value =
                accountHolderName.value;
        }

        document.getElementById('server_response').submit();
        return false;
    };

    failedResponseHandler = (error) => {
        this.showError(error.message || 'Tokenization failed.');

        let payNowButton = document.getElementById('pay-now');
        if (payNowButton) {
            payNowButton.disabled = false;
            payNowButton.querySelector('svg').classList.add('hidden');
            payNowButton.querySelector('span').classList.remove('hidden');
        }

        return false;
    };

    showError(message) {
        document.getElementById('lawpay_errors').innerHTML =
            '<div class="alert alert-failure mb-4"><ul><li>' +
            message +
            '</li></ul></div>';
    }

    completePaymentUsingToken() {
        let payNowButton = document.getElementById('pay-now');
        if (payNowButton) {
            payNowButton.disabled = true;
            payNowButton.querySelector('svg').classList.remove('hidden');
            payNowButton.querySelector('span').classList.add('hidden');
        }

        document.getElementById('server_response').submit();
        return false;
    }

    handle = () => {
        // Handle saved token selection
        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (e) => {
                document
                    .getElementById('lawpay-ach-container')
                    ?.classList.add('hidden');

                document.querySelector('input[name=token]').value =
                    e.target.dataset.token;
            })
        );

        // Handle new bank account selection
        let toggleNewAccount = document.getElementById(
            'toggle-payment-with-new-bank-account'
        );
        if (toggleNewAccount) {
            toggleNewAccount.addEventListener('click', () => {
                document
                    .getElementById('lawpay-ach-container')
                    ?.classList.remove('hidden');

                document.querySelector('input[name=token]').value = '';
            });
        }

        let payNowButton = document.getElementById('pay-now');
        if (payNowButton) {
            payNowButton.addEventListener('click', (e) => {
                let tokenInput =
                    document.querySelector('input[name=token]');
                if (tokenInput && tokenInput.value) {
                    return this.completePaymentUsingToken();
                }

                this.handleAuthorization();
            });
        }

        return this;
    };
}

function boot() {
    const publicKey = document.querySelector(
        'meta[name="lawpay-public-key"]'
    ).content;

    new LawPayACH(publicKey).handle();
}

instant()
    ? boot()
    : wait('#lawpay-ach-payment').then(() => boot());
