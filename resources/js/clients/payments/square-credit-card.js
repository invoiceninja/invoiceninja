/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

class SquareCreditCard {
    constructor() {
        this.appId = document.querySelector('meta[name=square-appId]').content;
        this.locationId = document.querySelector(
            'meta[name=square-locationId]'
        ).content;
        this.isLoaded = false;
    }

    async init() {
        this.payments = Square.payments(this.appId, this.locationId);

        this.card = await this.payments.card();

        await this.card.attach('#card-container');

        this.isLoaded = true;

        let iframeContainer = document.querySelector(
            '.sq-card-iframe-container'
        );

        if (iframeContainer) {
            iframeContainer.setAttribute('style', '150px !important');
        }

        let toggleWithToken = document.querySelector(
            '.toggle-payment-with-token'
        );

        if (toggleWithToken) {
            document.getElementById('card-container').classList.add('hidden');
        }
    }

    async completePaymentWithoutToken(e) {
        document.getElementById('errors').hidden = true;

        let payNowButton = document.getElementById('pay-now');
        this.payNowButton = payNowButton;

        this.payNowButton.disabled = true;
        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        try {
            let result = await this.card.tokenize();

            if (result.status !== 'OK') {
                let errorMessage = result.errors?.[0]?.message
                    ?? 'An error occurred during payment processing.';

                this.showError(errorMessage);
                this.resetPayButton();
                return;
            }

            /* SCA */
            let verificationToken;

            const verificationDetails = {
                amount: document.querySelector('meta[name=amount]').content,
                billingContact: JSON.parse(
                    document.querySelector('meta[name=square_contact]').content
                ),
                currencyCode: document.querySelector('meta[name=currencyCode]')
                    .content,
                intent: 'CHARGE',
            };

            const verificationResults = await this.payments.verifyBuyer(
                result.token,
                verificationDetails
            );

            verificationToken = verificationResults.token;

            document.querySelector('input[name="verificationToken"]').value =
                verificationToken;
            document.getElementById('sourceId').value = result.token;

            let tokenBillingCheckbox = document.querySelector(
                'input[name="token-billing-checkbox"]:checked'
            );

            if (tokenBillingCheckbox) {
                document.querySelector('input[name="store_card"]').value =
                    tokenBillingCheckbox.value;
            }

            return document.getElementById('server_response').submit();
        } catch (error) {
            this.showError(error.message ?? 'An error occurred during payment processing.');
            this.resetPayButton();
        }
    }

    showError(message) {
        let errorsDiv = document.getElementById('errors');
        errorsDiv.textContent = message;
        errorsDiv.hidden = false;
    }

    resetPayButton() {
        this.payNowButton.disabled = false;
        this.payNowButton.querySelector('svg').classList.add('hidden');
        this.payNowButton.querySelector('span').classList.remove('hidden');
    }

    async completePaymentUsingToken(e) {
        e.target.parentElement.disabled = true;

        let payNowButton = document.getElementById('pay-now');
        this.payNowButton = payNowButton;

        this.payNowButton.disabled = true;

        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        return document.getElementById('server_response').submit();
    }

    /* SCA */
    async verifyBuyer(token) {
        const verificationDetails = {
            amount: document.querySelector('meta[name=amount]').content,
            billingContact: document.querySelector('meta[name=square_contact]')
                .content,
            currencyCode: document.querySelector('meta[name=currencyCode]')
                .content,
            intent: 'CHARGE',
        };

        const verificationResults = await this.payments.verifyBuyer(
            token,
            verificationDetails
        );

        return verificationResults.token;
    }

    async authorizeCard(e) {
        let authorizeButton = document.getElementById('authorize-card');
        this.payNowButton = authorizeButton;

        authorizeButton.disabled = true;
        authorizeButton.querySelector('svg').classList.remove('hidden');
        authorizeButton.querySelector('span').classList.add('hidden');

        document.getElementById('errors').hidden = true;

        try {
            let result = await this.card.tokenize();

            if (result.status !== 'OK') {
                let errorMessage = result.errors?.[0]?.message
                    ?? 'An error occurred while authorizing the card.';

                this.showError(errorMessage);
                this.resetPayButton();
                return;
            }

            /* SCA - verify with STORE intent for card-on-file */
            const verificationDetails = {
                amount: '0',
                billingContact: JSON.parse(
                    document.querySelector('meta[name=square_contact]').content
                ),
                currencyCode: document.querySelector('meta[name=currencyCode]')
                    .content,
                intent: 'STORE',
            };

            const verificationResults = await this.payments.verifyBuyer(
                result.token,
                verificationDetails
            );

            document.querySelector('input[name="verificationToken"]').value =
                verificationResults.token;

            document.getElementById('sourceId').value = result.token;

            return document.getElementById('server_response').submit();
        } catch (error) {
            this.showError(error.message ?? 'An error occurred while authorizing the card.');
            this.resetPayButton();
        }
    }

    async handle() {
        let isAuthorization = document.querySelector(
            'meta[name=only-authorization]'
        );

        if (isAuthorization) {
            return this.handleAuthorization();
        }

        return this.handlePayment();
    }

    async handleAuthorization() {
        await this.init().then(() => {
            document
                .getElementById('authorize-card')
                ?.addEventListener('click', (e) => this.authorizeCard(e));

            Array.from(document.getElementsByClassName('loader')).forEach(
                (element) => {
                    element.classList.add('hidden');
                }
            );
        });
    }

    async handlePayment() {
        document.getElementById('payment-list').classList.add('hidden');

        await this.init().then(() => {
            document
                .getElementById('pay-now')
                ?.addEventListener('click', (e) => {
                    let tokenInput =
                        document.querySelector('input[name=token]');

                    if (tokenInput.value) {
                        return this.completePaymentUsingToken(e);
                    }

                    return this.completePaymentWithoutToken(e);
                });

            Array.from(
                document.getElementsByClassName('toggle-payment-with-token')
            ).forEach((element) =>
                element.addEventListener('click', async (element) => {
                    document
                        .getElementById('card-container')
                        .classList.add('hidden');
                    document.getElementById(
                        'save-card--container'
                    ).style.display = 'none';
                    document.querySelector('input[name=token]').value =
                        element.target.dataset.token;
                })
            );

            document
                .getElementById('toggle-payment-with-credit-card')
                ?.addEventListener('click', async (element) => {
                    document
                        .getElementById('card-container')
                        .classList.remove('hidden');
                    document.getElementById(
                        'save-card--container'
                    ).style.display = 'grid';
                    document.querySelector('input[name=token]').value = '';
                });

            Array.from(document.getElementsByClassName('loader')).forEach(
                (element) => {
                    element.classList.add('hidden');
                }
            );

            document.getElementById('payment-list').classList.remove('hidden');
            document.getElementById('toggle-payment-with-credit-card')?.click();
        });

        /** @type {NodeListOf<HTMLInputElement>} */
        const first = document.querySelector('input[name="payment-type"]');

        if (first) {
            first.click();
        }
    }
}

function boot() {
    new SquareCreditCard().handle();   
}

instant() ? boot() : wait('#square-credit-card-payment').then(() => boot());
