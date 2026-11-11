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

class LawPayCreditCard {
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
                        selector: '#lawpay_card_number',
                        input: {
                            type: 'credit_card_number',
                            css: {
                                'font-size': '14px',
                                'font-family': 'inherit',
                            },
                        },
                    },
                    {
                        selector: '#lawpay_cvv',
                        input: {
                            type: 'cvv',
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
        const expMonth = document.getElementById('lawpay_exp_month')?.value.replace(/[^\d]/g, '');
        const expYear = document.getElementById('lawpay_exp_year')?.value.replace(/[^\d]/g, '');

        if (!expMonth || !expYear) {
            this.showError('Please enter a valid expiration date.');
            return;
        }

        let payNowButton = document.getElementById('pay-now');
        if (payNowButton) {
            payNowButton.disabled = true;
            payNowButton.querySelector('svg').classList.remove('hidden');
            payNowButton.querySelector('span').classList.add('hidden');
        }

        document.getElementById('exp_month').value = expMonth;
        document.getElementById('exp_year').value = expYear;

        const cardholderName = document.getElementById('cardholder_name')?.value || '';

        window.AffiniPay.hostedFields
            .getPaymentToken({
                exp_month: expMonth,
                exp_year: expYear,
                name: cardholderName,
            })
            .then(this.successResponseHandler)
            .catch(this.failedResponseHandler);

        return false;
    };

    successResponseHandler = (response) => {
        document.getElementById('payment_token').value = response.id;
        document.getElementById('card_brand').value =
            response.card_type || '';
        document.getElementById('last_4').value =
            response.last_four || '';

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
                    .getElementById('lawpay-credit-card-container')
                    ?.classList.add('hidden');

                document.querySelector('input[name=token]').value =
                    e.target.dataset.token;
            })
        );

        // Handle new card selection
        let toggleNewCard = document.getElementById(
            'toggle-payment-with-credit-card'
        );
        if (toggleNewCard) {
            toggleNewCard.addEventListener('click', () => {
                document
                    .getElementById('lawpay-credit-card-container')
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

    new LawPayCreditCard(publicKey).handle();
}

instant()
    ? boot()
    : wait('#lawpay-credit-card-payment').then(() => boot());
