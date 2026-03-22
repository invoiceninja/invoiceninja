/**
 * Invoice Ninja (https://invoiceninja.com)
 * Checkout.com Flow SDK — authorize (save card) form.
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

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
            errEl.textContent = 'Session is missing. Please refresh the page.';
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
                const form = document.getElementById('server_response');
                if (!form) return;
                const gatewayResponseInput = form.querySelector('input[name="gateway_response"]');
                if (gatewayResponseInput) {
                    gatewayResponseInput.value = JSON.stringify({ id: paymentResponse.id });
                }
                form.submit();
            },
            onError: (event) => {
                const errEl = document.getElementById('flow-error-message');
                if (errEl) {
                    errEl.textContent = event.detail?.message || 'Failed to add payment method. Please try again.';
                    errEl.classList.remove('hidden');
                }
            },
        });

        const flowComponent = checkout.create('flow');
        flowComponent.mount('#flow-container');
    } catch (err) {
        const errEl = document.getElementById('flow-error-message');
        if (errEl) {
            errEl.textContent = err?.message || 'Unable to load form. Please refresh the page.';
            errEl.classList.remove('hidden');
        }
    }
}

const flowContainer = document.getElementById('flow-container');
if (flowContainer) {
    initFlow();
}
