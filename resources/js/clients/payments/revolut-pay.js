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
 * Revolut Pay button. RevolutCheckout comes from Revolut's embed.js, loaded
 * by the view for the gateway's environment.
 *
 * Whether the account is saved for later charges is a mount option, so the
 * button is mounted again when the client changes the save choice.
 *
 * @see https://developer.revolut.com/docs/guides/merchant/accept-payments/online-payments/revolut-pay/web
 */
class RevolutPayButton {
    constructor() {
        this.form = document.getElementById('server-response');
        this.errors = document.getElementById('errors');
        this.target = document.getElementById('revolut-pay-button');
    }

    meta(name) {
        return document.querySelector(`meta[name="${name}"]`)?.content ?? '';
    }

    showError(message) {
        this.errors.textContent = message;
        this.errors.hidden = false;
    }

    shouldSave() {
        const choice = document.querySelector('input[name="token-billing-checkbox"]:checked');

        return choice?.value === 'true';
    }

    redirectUrl(save) {
        const url = new URL(this.meta('revolut-redirect-url'));
        url.searchParams.set('store_card', save ? 'true' : 'false');

        return url.toString();
    }

    async mount() {
        if (!this.target || this.target.dataset.mounted) {
            return;
        }

        this.target.dataset.mounted = 'true';

        document.querySelectorAll('input[name="token-billing-checkbox"]').forEach((input) => {
            input.addEventListener('change', () => this.render());
        });

        this.setupSavedAccounts();

        await this.render();
    }

    /** A saved account is charged by the server; the button pays a new one. */
    useToken(token) {
        this.form.querySelector('input[name="token"]').value = token ?? '';

        document.getElementById('revolut-pay-button-container')?.classList.toggle('hidden', token !== null);
        document.getElementById('pay-with-token-container')?.classList.toggle('hidden', token === null);

        const saveCard = document.getElementById('save-card--container');

        if (saveCard) {
            saveCard.style.display = token === null ? '' : 'none';
        }
    }

    setupSavedAccounts() {
        document.querySelectorAll('.toggle-payment-with-token').forEach((element) => {
            element.addEventListener('click', () => this.useToken(element.dataset.token));
        });

        document
            .getElementById('toggle-payment-with-revolut-pay')
            ?.addEventListener('click', () => this.useToken(null));

        const payNow = document.getElementById('pay-now');

        payNow?.addEventListener('click', (event) => {
            event.preventDefault();
            payNow.disabled = true;
            payNow.querySelector('svg')?.classList.remove('hidden');
            payNow.querySelector('span')?.classList.add('hidden');
            this.form.querySelector('input[name="store_card"]').value = 'false';
            this.form.submit();
        });

        const checked = document.querySelector('.toggle-payment-with-token:checked');

        this.useToken(checked ? checked.dataset.token : null);
    }

    async render() {
        this.instance?.destroy?.();
        this.target.innerHTML = '';
        this.errors.hidden = true;

        const save = this.shouldSave();

        // embed.js returns the instance directly, the npm loader a promise.
        this.instance = await Promise.resolve(
            RevolutCheckout.payments({
                publicToken: this.meta('revolut-public-key'),
                mode: this.meta('revolut-mode'),
            })
        );

        const revolutPay = this.instance.revolutPay;

        revolutPay.mount(this.target, {
            currency: this.meta('revolut-order-currency'),
            totalAmount: Number(this.meta('revolut-order-amount')),
            savePaymentMethodForMerchant: save,
            createOrder: () => Promise.resolve({ publicId: this.meta('revolut-order-token') }),
            mobileRedirectUrls: {
                success: this.redirectUrl(save),
                failure: this.redirectUrl(false),
                cancel: this.redirectUrl(false),
            },
        });

        revolutPay.on('payment', (event) => {
            if (event.type === 'success') {
                this.form.querySelector('input[name="store_card"]').value = save ? 'true' : 'false';
                this.form.submit();
            } else if (event.type === 'error') {
                this.showError(String(event.error?.message ?? event.error ?? 'Revolut Pay failed'));
            }
        });
    }
}

function boot() {
    new RevolutPayButton().mount().catch((error) => {
        const errors = document.getElementById('errors');

        if (errors) {
            errors.textContent = error?.message ?? String(error);
            errors.hidden = false;
        }
    });
}

instant() ? boot() : wait('#revolut-pay-payment').then(() => boot());
