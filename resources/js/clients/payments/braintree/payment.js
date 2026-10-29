/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

/** Shared lifecycle for the Braintree checkout components. */
export class BraintreePayment {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('form');
        this.abort_controller = new AbortController();
        this.instances = [];
        this.busy = false;
    }

    find(selector) {
        return this.root.querySelector(selector);
    }

    on(element, event, callback) {
        element?.addEventListener(event, callback, {
            signal: this.abort_controller.signal,
        });
    }

    showError(error) {
        const errors = this.find('#errors');
        if (errors) {
            errors.textContent =
                error?.message || this.root.dataset.errorMessage;
            errors.hidden = false;
        }
    }

    setBusy(busy) {
        this.busy = busy;
        this.root.querySelectorAll('button').forEach((button) => {
            button.disabled = busy;
            button.querySelector('svg')?.classList.toggle('hidden', !busy);
            button.querySelector('span')?.classList.toggle('hidden', busy);
        });
        this.root
            .querySelectorAll('input[type=radio]')
            .forEach((input) => (input.disabled = busy));
    }

    async keep(instance) {
        if (!this.root.isConnected) {
            await instance.teardown?.();
            throw new Error('Payment form was closed.');
        }
        this.instances.push(instance);
        return instance;
    }

    async createClient(collect_device_data = true) {
        const client = await this.keep(
            await window.braintree.client.create({
                authorization: this.find('meta[name=client-token]').content,
            })
        );
        if (collect_device_data) {
            const collector = await this.keep(
                await window.braintree.dataCollector.create({ client })
            );
            this.find('input[name=client-data]').value = collector.deviceData;
        }
        return client;
    }

    selectToken(token, new_method_selector) {
        this.find('input[name=token]').value = token;
        this.find(new_method_selector)?.classList.toggle(
            'hidden',
            Boolean(token)
        );
        this.find('#save-card--container')?.classList.toggle(
            'hidden',
            Boolean(token)
        );
        this.find('#pay-now')?.classList.toggle('hidden', Boolean(token));
        this.find('#pay-now-with-token')?.classList.toggle('hidden', !token);
    }

    bindSelection(new_method_selector) {
        this.root
            .querySelectorAll('.toggle-payment-with-token')
            .forEach((input) => {
                this.on(input, 'change', () =>
                    this.selectToken(input.dataset.token, new_method_selector)
                );
            });
        this.on(this.find('#toggle-payment-with-credit-card'), 'change', () =>
            this.selectToken('', new_method_selector)
        );
        const first_token = this.find('.toggle-payment-with-token');
        if (first_token) {
            first_token.checked = true;
            this.selectToken(first_token.dataset.token, new_method_selector);
        }
    }

    submit(payload) {
        if (!this.root.isConnected) return;
        if (payload)
            this.find('input[name=gateway_response]').value =
                JSON.stringify(payload);
        const store_card = this.find('input[name=store_card]');
        if (store_card)
            store_card.value =
                this.find('input[name="token-billing-checkbox"]:checked')
                    ?.value || 'false';
        this.form.submit();
    }

    async teardown() {
        this.abort_controller.abort();
        await Promise.allSettled(
            this.instances
                .splice(0)
                .reverse()
                .map((instance) => instance.teardown?.())
        );
    }
}

// Livewire assets execute once; observe replacement roots as well as the first mount.
export function mountBraintree(selector, create_payment) {
    const mounted = new Map();
    const mount = () => {
        for (const [root, payment] of mounted) {
            if (!root.isConnected) {
                mounted.delete(root);
                payment.teardown();
            }
        }
        document.querySelectorAll(selector).forEach((root) => {
            if (mounted.has(root)) return;
            const initialize = () => {
                const payment = create_payment(root);
                mounted.set(root, payment);
                payment.handle().catch(async (error) => {
                    await payment.teardown();
                    if (!root.isConnected) return;
                    payment.showError(error);
                    payment.setBusy(true);
                    const retry_button = document.createElement('button');
                    retry_button.type = 'button';
                    retry_button.textContent = root.dataset.retryMessage;
                    retry_button.className = 'button button-primary bg-primary';
                    retry_button.addEventListener(
                        'click',
                        () => {
                            retry_button.remove();
                            payment.find('#errors').hidden = true;
                            if (error instanceof TypeError) {
                                window.location.reload();
                            } else {
                                initialize();
                            }
                        },
                        { once: true }
                    );
                    payment.find('#errors')?.append(retry_button);
                });
            };
            initialize();
        });
    };
    new MutationObserver(mount).observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
    mount();
}
