/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait.js';

export function buildVerificationDetails(
    intent,
    billingContact,
    amount = null,
    currencyCode = null
) {
    const verificationDetails = {
        billingContact,
        customerInitiated: true,
        intent,
        sellerKeyedIn: false,
    };

    if (intent !== 'STORE') {
        verificationDetails.amount = amount;
        verificationDetails.currencyCode = currencyCode;
    }

    return verificationDetails;
}

export async function tokenizeCard(
    card,
    verificationDetails,
    storedCardId = null
) {
    const result = storedCardId
        ? await card.tokenize(verificationDetails, storedCardId)
        : await card.tokenize(verificationDetails);

    if (result.status !== 'OK') {
        throw new Error(
            result.errors?.[0]?.message ??
                'An error occurred during payment processing.'
        );
    }

    return result.token;
}

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
            const shouldStoreCard =
                document.querySelector(
                    'input[name="token-billing-checkbox"]:checked'
                )?.value === 'true';

            document.querySelector('input[name="store_card"]').value =
                shouldStoreCard ? 'true' : 'false';

            const sourceId = await tokenizeCard(
                this.card,
                this.verificationDetails(
                    shouldStoreCard ? 'CHARGE_AND_STORE' : 'CHARGE'
                )
            );

            document.getElementById('sourceId').value = sourceId;

            return document.getElementById('server_response').submit();
        } catch (error) {
            this.showError(
                error.message ?? 'An error occurred during payment processing.'
            );
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
        document.querySelector('input[name="store_card"]').value = 'false';

        let payNowButton = document.getElementById('pay-now');
        this.payNowButton = payNowButton;

        this.payNowButton.disabled = true;

        this.payNowButton.querySelector('svg').classList.remove('hidden');
        this.payNowButton.querySelector('span').classList.add('hidden');

        document.getElementById('errors').hidden = true;

        try {
            const storedCardId =
                document.querySelector('input[name=token]').value;
            const sourceId = await tokenizeCard(
                this.card,
                this.verificationDetails('CHARGE'),
                storedCardId
            );

            document.getElementById('sourceId').value = sourceId;

            return document.getElementById('server_response').submit();
        } catch (error) {
            this.showError(
                error.message ?? 'An error occurred during payment processing.'
            );
            this.resetPayButton();
        }
    }

    async authorizeCard(e) {
        let authorizeButton = document.getElementById('authorize-card');
        this.payNowButton = authorizeButton;

        authorizeButton.disabled = true;
        authorizeButton.querySelector('svg').classList.remove('hidden');
        authorizeButton.querySelector('span').classList.add('hidden');

        document.getElementById('errors').hidden = true;

        try {
            const sourceId = await tokenizeCard(
                this.card,
                this.verificationDetails('STORE')
            );

            document.getElementById('sourceId').value = sourceId;

            return document.getElementById('server_response').submit();
        } catch (error) {
            this.showError(
                error.message ?? 'An error occurred while authorizing the card.'
            );
            this.resetPayButton();
        }
    }

    verificationDetails(intent) {
        const billingContact = JSON.parse(
            document.querySelector('meta[name=square_contact]').content
        );
        const amount = document.querySelector('meta[name=amount]')?.content;
        const currencyCode = document.querySelector(
            'meta[name=currencyCode]'
        )?.content;

        return buildVerificationDetails(
            intent,
            billingContact,
            amount,
            currencyCode
        );
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

export function resolveSquarePaymentContainer(documentRoot) {
    return (
        documentRoot.getElementById('square-credit-card-payment') ??
        documentRoot.getElementById('server_response')
    );
}

export async function bootSquareCreditCard(
    documentRoot,
    createSquareCreditCard = () => new SquareCreditCard()
) {
    const container = resolveSquarePaymentContainer(documentRoot);

    if (!container || container.dataset.squareInitialized === 'true') {
        return false;
    }

    container.dataset.squareInitialized = 'true';

    try {
        await createSquareCreditCard().handle();

        return true;
    } catch (error) {
        delete container.dataset.squareInitialized;

        const errors = documentRoot.getElementById('errors');

        if (errors) {
            errors.textContent =
                error?.message ??
                'Square could not initialize. Please refresh and try again.';
            errors.hidden = false;
        }

        Array.from(documentRoot.getElementsByClassName('loader')).forEach(
            (element) => element.classList.add('hidden')
        );

        return false;
    }
}

if (typeof document !== 'undefined') {
    instant()
        ? void bootSquareCreditCard(document)
        : void wait('#square-credit-card-payment').then(() =>
              bootSquareCreditCard(document)
          );
}
