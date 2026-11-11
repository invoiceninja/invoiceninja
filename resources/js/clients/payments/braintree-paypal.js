/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { BraintreePayment, mountBraintree } from './braintree/payment';

class BraintreePayPal extends BraintreePayment {
    setBusy(busy) {
        super.setBusy(busy);
        this.find('#paypal-spinner')?.classList.toggle('hidden', !busy);
    }

    async handle() {
        this.setBusy(true);
        this.bindSelection('#paypal-button');
        const client = await this.createClient();
        const checkout = await this.keep(
            await window.braintree.paypalCheckout.create({ client })
        );
        await checkout.loadPayPalSDK({ vault: true });
        if (!this.root.isConnected) return;
        const buttons = window.paypal.Buttons({
            fundingSource: window.paypal.FUNDING.PAYPAL,
            createBillingAgreement: async () => {
                this.setBusy(true);
                try {
                    return await checkout.createPayment({ flow: 'vault' });
                } catch (error) {
                    this.setBusy(false);
                    throw error;
                }
            },
            onApprove: async (data) => {
                if (this.submitted || this.approving) return;
                this.approving = true;
                this.setBusy(true);
                try {
                    const payload = await checkout.tokenizePayment(data);
                    this.submitted = true;
                    this.submit(payload);
                } catch (error) {
                    this.approving = false;
                    this.showError(error);
                    this.setBusy(false);
                }
            },
            onCancel: () => this.setBusy(false),
            onError: (error) => {
                this.showError(error);
                this.setBusy(false);
            },
        });
        this.instances.push({ teardown: () => buttons.close() });
        await buttons.render(this.find('#paypal-button'));
        this.on(this.find('#pay-now-with-token'), 'click', () => {
            if (this.busy) return;
            this.setBusy(true);
            this.submit();
        });
        this.setBusy(false);
    }
}

mountBraintree(
    '#braintree-paypal-payment',
    (root) => new BraintreePayPal(root)
);
