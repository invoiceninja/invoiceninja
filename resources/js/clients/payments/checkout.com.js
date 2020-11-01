/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

document
    .getElementById('toggle-payment-with-token')
    .addEventListener('click', () => {
        document.getElementById('save-card--container').style.display = 'none';
        document.getElementById('checkout--container').style.display = 'none';
        document.getElementById('pay-now-with-token--container').style.display =
            'block';

        document.querySelector('input[name=pay_with_token]').value = true;
    });

document
    .getElementById('toggle-payment-with-credit-card')
    .addEventListener('click', () => {
        document.getElementById('save-card--container').style.display = 'grid';
        document.getElementById('checkout--container').style.display = 'block';
        document.getElementById('pay-now-with-token--container').style.display =
            'none';

        document.querySelector('input[name=pay_with_token]').value = false;
    });

const completePayment = (data = null) => {
    if (data) {
        document.querySelector(
            'input[name="gateway_response"]'
        ).value = JSON.stringify(data.data);
    }

    document.querySelector(
        'input[name="store_card"]'
    ).value = document.querySelector(
        'input[name=token-billing-checkbox]:checked'
    ).value;

    document.getElementById('server-response').submit();
};

document
    .getElementById('pay-now-with-token')
    .addEventListener('click', completePayment);

window.CKOConfig = {
    publicKey: document.querySelector('meta[name="public-key"]').content,
    customerEmail: document.querySelector('meta[name="customer-email"]')
        .content,
    value: document.querySelector('meta[name="value"]').content,
    currency: document.querySelector('meta[name="currency"]').content,
    paymentMode: 'cards',
    cardFormMode: 'cardTokenisation',
    cardTokenised: function(event) {
        completePayment(event);
    },
};
