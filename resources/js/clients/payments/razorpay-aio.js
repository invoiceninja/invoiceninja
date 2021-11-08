/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

let options = JSON.parse(
    document.querySelector('meta[name=razorpay-options]')?.content
);

options.handler = function(response) {
    document.getElementById('razorpay_payment_id').value =
        response.razorpay_payment_id;
    document.getElementById('razorpay_signature').value =
        response.razorpay_signature;
    document.getElementById('server-response').submit();
};

let razorpay = new Razorpay(options);

document.getElementById('pay-now').onclick = function(event) {
    event.target.parentElement.disabled = true;

    razorpay.open();
};
