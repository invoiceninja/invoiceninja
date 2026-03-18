/**
 * Invoice Ninja (https://invoiceninja.com)
 * Checkout.com Flow SDK — payment form (replaces Frames when processingChannelId is set).
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';
import { loadCheckoutWebComponents } from '@checkout.com/checkout-web-components';

function getMeta(name) {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? el.getAttribute('content') || '' : '';
}

async function initFlow() {
    const sessionId = getMeta('payment-session-id');
    const sessionToken = getMeta('payment-session-token');
    const publicKey = getMeta('public-key');
    const environment = getMeta('environment') || 'sandbox';

    if (!sessionId || !sessionToken || !publicKey) {
        const errEl = document.getElementById('flow-error-message');
        if (errEl) {
            errEl.textContent = 'Payment session is missing. Please refresh the page.';
            errEl.classList.remove('hidden');
        }
        return;
    }

    const paymentSession = {
        id: sessionId,
        payment_session_token: sessionToken,
    };

    try {
        const checkout = await loadCheckoutWebComponents({
            paymentSession,
            publicKey,
            environment,
            onPaymentCompleted: (_self, paymentResponse) => {
                const form = document.getElementById('server-response');
                if (!form) return;
                const gatewayResponseInput = form.querySelector('input[name="gateway_response"]');
                const storeCardInput = form.querySelector('input[name="store_card"]');
                if (gatewayResponseInput) {
                    gatewayResponseInput.value = JSON.stringify({
                        id: paymentResponse.id,
                    });
                }
                if (storeCardInput) {
                    const saveCheckbox = document.querySelector('input[name=token-billing-checkbox]:checked');
                    storeCardInput.value = saveCheckbox ? saveCheckbox.value : 'false';
                }
                form.submit();
            },
            onError: (event) => {
                const errEl = document.getElementById('flow-error-message');
                if (errEl) {
                    errEl.textContent = event.detail?.message || 'Payment failed. Please try again.';
                    errEl.classList.remove('hidden');
                }
            },
        });

        const flowComponent = checkout.create('flow');
        flowComponent.mount('#flow-container');
    } catch (err) {
        const errEl = document.getElementById('flow-error-message');
        if (errEl) {
            errEl.textContent = err?.message || 'Unable to load payment form. Please refresh the page.';
            errEl.classList.remove('hidden');
        }
    }
}

function bootFlow() {
    initFlow();
}

function setupTokenToggles() {
    const flowContainer = document.getElementById('flow-container');
    const tokenContainer = document.getElementById('pay-now-with-token--container');
    const saveCardContainer = document.getElementById('save-card--container');

    Array.from(document.getElementsByClassName('toggle-payment-with-token') || []).forEach((el) => {
        el.addEventListener('click', () => {
            if (flowContainer) flowContainer.classList.add('hidden');
            if (tokenContainer) tokenContainer.classList.remove('hidden');
            if (saveCardContainer) saveCardContainer.style.display = 'none';
            const tokenInput = document.querySelector('input[name=token]');
            if (tokenInput) tokenInput.value = el.dataset.token || '';
        });
    });

    const newCardRadio = document.getElementById('toggle-payment-with-credit-card');
    if (newCardRadio) {
        newCardRadio.addEventListener('click', () => {
            if (flowContainer) flowContainer.classList.remove('hidden');
            if (tokenContainer) tokenContainer.classList.add('hidden');
            if (saveCardContainer) saveCardContainer.style.display = 'grid';
            const tokenInput = document.querySelector('input[name=token]');
            if (tokenInput) tokenInput.value = '';
        });
    }

    const payNowBtn = document.getElementById('pay-now-with-token');
    if (payNowBtn) {
        payNowBtn.addEventListener('click', (e) => {
            e.preventDefault();
            payNowBtn.disabled = true;
            const svg = payNowBtn.querySelector('svg');
            const span = payNowBtn.querySelector('span');
            if (svg) svg.classList.remove('hidden');
            if (span) span.classList.add('hidden');
            document.getElementById('server-response').submit();
        });
    }
}

function boot() {
    const flowContainer = document.getElementById('flow-container');
    if (flowContainer) {
        setupTokenToggles();
        bootFlow();

        /** @type {NodeListOf<HTMLInputElement>} */
        const tokens = document.querySelectorAll('input.toggle-payment-with-token');
        if (tokens.length > 0) {
            tokens[0].click();
        }
    }
}

instant() ? boot() : wait('#checkout-credit-card-payment').then(() => boot());
