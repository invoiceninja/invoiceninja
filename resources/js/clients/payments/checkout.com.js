/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

const paymentWithToken = document.getElementById('toggle-payment-with-token');
const paymentWithCreditCard = document.getElementById(
    'toggle-payment-with-credit-card'
);
const payButton = document.getElementById('pay-button');
const form = document.getElementById('payment-form');
const publicKey =
    document.querySelector('meta[name="public-key"]').content ?? '';

if (paymentWithToken) {
    paymentWithToken.addEventListener('click', () => {
        document.getElementById('save-card--container').style.display = 'none';
        document.getElementById('checkout--container').style.display = 'none';
        document.getElementById('pay-now-with-token--container').style.display =
            'block';

        document.querySelector('input[name=pay_with_token]').value = true;
    });
}

if (paymentWithCreditCard) {
    paymentWithCreditCard.addEventListener('click', () => {
        document.getElementById('save-card--container').style.display = 'grid';
        document.getElementById('checkout--container').style.display = 'block';
        document.getElementById('pay-now-with-token--container').style.display =
            'none';

        document.querySelector('input[name=pay_with_token]').value = false;
    });
}

const completePayment = (data = null) => {
    if (data) {
        document.querySelector(
            'input[name="gateway_response"]'
        ).value = JSON.stringify(data);
    }

    document.querySelector(
        'input[name="store_card"]'
    ).value = document.querySelector(
        'input[name=token-billing-checkbox]:checked'
    ).value;

    document.getElementById('server-response').submit();
};

if (document.getElementById('pay-now-with-token')) {
    document
        .getElementById('pay-now-with-token')
        .addEventListener('click', completePayment);
}

// window.CKOConfig = {
//     publicKey: document.querySelector('meta[name="public-key"]').content,
//     customerEmail: document.querySelector('meta[name="customer-email"]')
//         .content,
//     value: document.querySelector('meta[name="value"]').content,
//     currency: document.querySelector('meta[name="currency"]').content,
//     paymentMode: 'cards',
//     cardFormMode: 'cardTokenisation',
//     cardTokenised: function(event) {
//         completePayment(event);
//     },
// };

Frames.init(publicKey);

Frames.addEventHandler(Frames.Events.CARD_VALIDATION_CHANGED, function(event) {
    payButton.disabled = !Frames.isCardValid();
});

Frames.addEventHandler(Frames.Events.CARD_TOKENIZED, function(event) {
    completePayment(event)
});

form.addEventListener('submit', function(event) {
    event.preventDefault();
    Frames.submitCard();
});
