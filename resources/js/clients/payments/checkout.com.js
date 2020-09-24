/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

window.CKOConfig = {
    publicKey: document.querySelector('meta[name="public-key"]').content,
    customerEmail: document.querySelector('meta[name="customer-email"]')
        .content,
    value: document.querySelector('meta[name="value"]').content,
    currency: document.querySelector('meta[name="currency"]').content,
    paymentMode: 'cards',
    cardFormMode: 'cardTokenisation',
    cardTokenised: function(event) {
        document.querySelector(
            'input[name="gateway_response"]'
        ).value = JSON.stringify(event.data);

        document.querySelector(
            'input[name="store_card"]'
        ).value = document.querySelector('input[name=token-billing-checkbox]:checked').value;
        
        document.getElementById('server-response').submit();
    },
};
