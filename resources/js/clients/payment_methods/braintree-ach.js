/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import {
    BraintreePayment,
    mountBraintree,
} from '../payments/braintree/payment';

class BraintreeACH extends BraintreePayment {
    async handle() {
        if (this.root.id === 'braintree-ach-payment') {
            const button = this.find('#pay-now');
            if (!button) return;
            const tokens = this.root.querySelectorAll(
                '.toggle-payment-with-token'
            );
            const selectToken = (input) =>
                (this.find('input[name=source]').value = input.dataset.token);
            tokens.forEach((input) =>
                this.on(input, 'change', () => selectToken(input))
            );
            if (tokens[0]) {
                tokens[0].checked = true;
                selectToken(tokens[0]);
            }
            this.on(button, 'click', () => {
                if (this.busy) return;
                this.setBusy(true);
                this.submit();
            });
            return;
        }

        this.setBusy(true);
        const client = await this.createClient(false);
        const bank_account = await this.keep(
            await window.braintree.usBankAccount.create({ client })
        );
        this.on(this.find('#authorize-bank-account'), 'click', async () => {
            if (this.busy) return;
            if (!this.find('#accept-terms').checked) {
                this.showError({ message: this.root.dataset.mandateError });
                return;
            }
            for (const input of this.root.querySelectorAll('[required]')) {
                if (!input.reportValidity()) return;
            }
            this.setBusy(true);
            this.find('#errors').hidden = true;
            const value = (id) => this.find(`#${id}`).value.trim();
            const bank_details = {
                accountNumber: value('account-number'),
                routingNumber: value('routing-number'),
                accountType: this.find('input[name=account-type]:checked')
                    .value,
                ownershipType: this.find('input[name=ownership-type]:checked')
                    .value,
                billingAddress: {
                    streetAddress: value('billing-street-address'),
                    extendedAddress: value('billing-extended-address'),
                    locality: value('billing-locality'),
                    region: value('billing-region'),
                    postalCode: value('billing-postal-code'),
                },
            };
            if (bank_details.ownershipType === 'personal') {
                const [first_name, ...last_name] = value(
                    'account-holder-name'
                ).split(/\s+/);
                bank_details.firstName = first_name;
                bank_details.lastName = last_name.join(' ');
            } else {
                bank_details.businessName = value('account-holder-name');
            }
            try {
                const payload = await bank_account.tokenize({
                    bankDetails: bank_details,
                    mandateText: this.find('label[for=accept-terms]')
                        .textContent.trim()
                        .replace(/\s+/g, ' '),
                });
                this.find('input[name=nonce]').value = payload.nonce;
                this.submit();
            } catch (error) {
                this.showError(error);
                this.setBusy(false);
            }
        });
        this.setBusy(false);
    }
}

mountBraintree(
    '#braintree-ach-authorize, #braintree-ach-payment',
    (root) => new BraintreeACH(root)
);
