/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

function boot() {
    const checkoutUrl = document.querySelector('meta[name="revolut-checkout-url"]')?.content;

    if (!checkoutUrl) {
        console.error('[Revolut] Missing checkout URL');
        return;
    }

    const payNowButton = document.getElementById('pay-now');

    if (!payNowButton) {
        console.error('[Revolut] Missing pay-now button');
        return;
    }

    payNowButton.addEventListener('click', function (event) {
        event.preventDefault();

        payNowButton.disabled = true;

        // Redirect to Revolut's hosted checkout page
        window.location.href = checkoutUrl;
    });
}

instant() ? boot() : wait('#revolut-hosted-payment').then(() => boot());
