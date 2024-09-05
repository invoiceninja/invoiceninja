/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

import { wait, instant } from '../wait';

function boot() {
    let options = JSON.parse(
        document.querySelector('meta[name=razorpay-options]')?.content
    );
    
    options.handler = function (response) {
        document.getElementById('razorpay_payment_id').value =
            response.razorpay_payment_id;
        document.getElementById('razorpay_signature').value =
            response.razorpay_signature;
        document.getElementById('server-response').submit();
    };
    
    options.modal = {
        ondismiss: function () {
            payNowButton.disabled = false;
        },
    };
    
    let razorpay = new Razorpay(options);
    let payNowButton = document.getElementById('pay-now');
    
    payNowButton.onclick = function (event) {
        payNowButton.disabled = true;
    
        razorpay.open();
    };
}

instant() ? boot() : wait('#razorpay-hosted-payment').then(() => boot());
