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

class BraintreeCreditCard extends BraintreePayment {
    async handle() {
        this.setBusy(true);
        this.bindSelection('#braintree-hosted-fields');
        const client = await this.createClient();
        const challenges =
            client.getConfiguration().gatewayConfiguration.challenges || [];
        const requires_cvv =
            challenges.includes('cvv') ||
            this.root.dataset.requireCvv === 'true';
        const requires_postal_code = challenges.includes('postal_code');
        this.hosted_fields = await this.keep(
            await window.braintree.hostedFields.create({
                client,
                styles: {
                    input: { 'font-size': '16px', color: '#374151' },
                    ':focus': { color: '#111827' },
                    '.invalid': { color: '#b91c1c' },
                },
                fields: {
                    number: {
                        container: this.find('#braintree-card-number'),
                        internalLabel: this.find(
                            'label[for=braintree-card-number]'
                        ).textContent,
                    },
                    expirationDate: {
                        container: this.find('#braintree-expiration-date'),
                        internalLabel: this.find(
                            'label[for=braintree-expiration-date]'
                        ).textContent,
                    },
                    ...(requires_cvv
                        ? {
                              cvv: {
                                  container: this.find('#braintree-cvv'),
                                  internalLabel: this.find(
                                      'label[for=braintree-cvv]'
                                  ).textContent,
                              },
                          }
                        : {}),
                    ...(requires_postal_code
                        ? {
                              postalCode: {
                                  container: this.find(
                                      '#braintree-postal-code'
                                  ),
                                  internalLabel: this.find(
                                      'label[for=braintree-postal-code]'
                                  ).textContent,
                              },
                          }
                        : {}),
                },
            })
        );
        this.find('#braintree-cvv-container').hidden = !requires_cvv;
        this.find('#braintree-postal-code-container').hidden =
            !requires_postal_code;
        if (this.find('input[name=threeds_enable]').value === 'true') {
            this.three_d_secure = await this.keep(
                await window.braintree.threeDSecure.create({
                    client,
                    version: '2',
                })
            );
        }
        this.on(this.find('#pay-now'), 'click', () => this.pay());
        this.on(this.find('#pay-now-with-token'), 'click', () => this.pay());
        this.setBusy(false);
    }

    async pay() {
        if (this.busy) return;
        this.setBusy(true);
        this.find('#errors').hidden = true;
        const token = this.find('input[name=token]').value;
        try {
            let payload;
            if (token && !this.three_d_secure) {
                this.submit();
                return;
            }
            if (token) {
                const selected = this.find(
                    '.toggle-payment-with-token:checked'
                );
                payload = {
                    nonce: selected.dataset.nonce,
                    details: { bin: selected.dataset.bin },
                };
            } else {
                payload = await this.hosted_fields.tokenize();
            }
            if (this.three_d_secure) {
                const parameters = JSON.parse(
                    this.find('input[name=threeds]').value
                );
                payload = await this.three_d_secure.verifyCard({
                    ...parameters,
                    nonce: payload.nonce,
                    bin: payload.details.bin,
                    challengeRequested: true,
                    onLookupComplete: (data, next) => next(),
                });
                if (!payload.liabilityShifted)
                    throw new Error(this.root.dataset.verificationError);
            }
            this.submit(payload);
        } catch (error) {
            this.showError(error);
            this.setBusy(false);
            if (token && this.three_d_secure) {
                // A saved-card nonce can be consumed by authentication. Fetch a fresh one on retry.
                this.find('#pay-now-with-token').disabled = true;
                const retry_button = document.createElement('button');
                retry_button.type = 'button';
                retry_button.className = 'button button-primary bg-primary';
                retry_button.textContent = this.root.dataset.retryMessage;
                this.on(retry_button, 'click', () => window.location.reload());
                this.find('#errors').append(retry_button);
            }
        }
    }
}

mountBraintree(
    '#braintree-credit-card-payment',
    (root) => new BraintreeCreditCard(root)
);
