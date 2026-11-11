/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

/**
 * Revolut card field, used to pay with a new card, to pay with a saved card,
 * and on the "add payment method" page (a zero-amount order). RevolutCheckout
 * comes from Revolut's embed.js, loaded by the view for the gateway's
 * environment.
 *
 * @see https://developer.revolut.com/docs/guides/merchant/accept-payments/online-payments/card-field/web
 */
class RevolutCreditCard {
    constructor() {
        this.form = document.getElementById('server-response');
        this.payNow = document.getElementById('pay-now');
        this.errors = document.getElementById('errors');
        this.target = document.getElementById('revolut-card-field');
        this.onlyAuthorization = this.meta('revolut-only-authorization') === 'true';
        this.token = null;
    }

    meta(name) {
        return document.querySelector(`meta[name="${name}"]`)?.content ?? '';
    }

    setLoading(loading) {
        this.payNow.disabled = loading;
        this.payNow.querySelector('svg')?.classList.toggle('hidden', !loading);
        this.payNow.querySelector('span')?.classList.toggle('hidden', loading);
    }

    showError(message) {
        this.errors.textContent = message;
        this.errors.hidden = false;
        this.setLoading(false);
    }

    shouldSaveCard() {
        if (this.onlyAuthorization) {
            return true;
        }

        const choice = document.querySelector('input[name="token-billing-checkbox"]:checked');

        return choice?.value === 'true';
    }

    /** Show the card field or pay with the chosen saved card. */
    useToken(token) {
        this.token = token;

        document.getElementById('revolut-card-field-container')?.classList.toggle('hidden', token !== null);

        const saveCard = document.getElementById('save-card--container');

        if (saveCard) {
            saveCard.style.display = token === null ? '' : 'none';
        }
    }

    setupTokens() {
        document.querySelectorAll('.toggle-payment-with-token').forEach((element) => {
            element.addEventListener('click', () => this.useToken(element.dataset.token));
        });

        document
            .getElementById('toggle-payment-with-credit-card')
            ?.addEventListener('click', () => this.useToken(null));

        const checked = document.querySelector('.toggle-payment-with-token:checked');

        this.useToken(checked ? checked.dataset.token : null);
    }

    payWithToken() {
        this.form.querySelector('input[name="token"]').value = this.token;
        this.form.querySelector('input[name="store_card"]').value = 'false';
        this.form.submit();
    }

    async mount() {
        if (!this.target || this.target.dataset.mounted) {
            return;
        }

        this.target.dataset.mounted = 'true';

        this.setupTokens();

        const instance = await RevolutCheckout(this.meta('revolut-order-token'), this.meta('revolut-mode'));

        this.card = instance.createCardField({
            target: this.target,
            hidePostcodeField: true,
            onSuccess: () => this.complete(),
            onError: (error) => this.showError(String(error?.message ?? error).replace('RevolutCheckout: ', '')),
            onCancel: () => this.setLoading(false),
            onValidation: (errors) => {
                if (errors.length) {
                    this.showError(errors.map((error) => error.message).join(' '));
                } else {
                    this.errors.hidden = true;
                }
            },
        });

        this.payNow.addEventListener('click', (event) => {
            event.preventDefault();
            this.errors.hidden = true;
            this.setLoading(true);

            if (this.token) {
                this.payWithToken();

                return;
            }

            const meta = {
                name: this.meta('revolut-cardholder-name'),
                email: this.meta('revolut-customer-email') || undefined,
            };

            if (this.shouldSaveCard()) {
                meta.savePaymentMethodFor = 'merchant';
            }

            this.card.submit(meta);
        });
    }

    complete() {
        const storeCard = this.form.querySelector('input[name="store_card"]');

        if (storeCard) {
            storeCard.value = this.shouldSaveCard() ? 'true' : 'false';
        }

        this.form.submit();
    }
}

function boot() {
    new RevolutCreditCard().mount().catch((error) => {
        const errors = document.getElementById('errors');

        if (errors) {
            errors.textContent = error?.message ?? String(error);
            errors.hidden = false;
        }
    });
}

instant() ? boot() : wait('#revolut-credit-card-payment').then(() => boot());
