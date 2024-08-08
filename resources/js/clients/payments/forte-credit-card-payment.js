/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

import { wait, instant } from '../wait';
import { SimpleCard } from '@invoiceninja/simple-card';

class ForteAuthorizeCard {
    constructor(apiLoginId) {
        this.apiLoginId = apiLoginId;
        this.cardHolderName = document.getElementById('cardholder_name');

        this.sc = new SimpleCard({
            fields: {
                card: {
                    number: '#number',
                    date: '#date',
                    cvv: '#cvv',
                },
            },
        });

        this.sc.mount();
    }

    handleAuthorization = () => {
        const data = {
            api_login_id: this.apiLoginId,
            card_number: this.sc.value('number')?.replace(/[^\d]/g, ''),
            expire_year: `20${this.sc.value('year')?.replace(/[^\d]/g, '')}`,
            expire_month: this.sc.value('month')?.replace(/[^\d]/g, ''),
            cvv: this.sc.value('cvv')?.replace(/[^\d]/g, ''),
        };

        let payNowButton = document.getElementById('pay-now');

        if (payNowButton) {
            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.remove('hidden');
            document.querySelector('#pay-now > span').classList.add('hidden');
        }

        forte
            .createToken(data)
            .success(this.successResponseHandler)
            .error(this.failedResponseHandler);
        return false;
    };

    successResponseHandler = (response) => {
        document.getElementById('payment_token').value = response.onetime_token;
        document.getElementById('card_brand').value = response.card_type;

        document.getElementById('server_response').submit();

        return false;
    };

    failedResponseHandler = (response) => {
        var errors =
            '<div class="alert alert-failure mb-4"><ul><li>' +
            response.response_description +
            '</li></ul></div>';
        document.getElementById('forte_errors').innerHTML = errors;
        document.getElementById('pay-now').disabled = false;
        document.querySelector('#pay-now > svg').classList.add('hidden');
        document.querySelector('#pay-now > span').classList.remove('hidden');

        return false;
    };

    handle = () => {
        let payNowButton = document.getElementById('pay-now');

        if (payNowButton) {
            payNowButton.addEventListener('click', (e) => {
                this.handleAuthorization();
            });
        }

        return this;
    };
}

function boot() {
    const apiLoginId = document.querySelector(
        'meta[name="forte-api-login-id"]'
    ).content;

    /** @handle */
    new ForteAuthorizeCard(apiLoginId).handle();
}

instant() ? boot() : wait('#forte-credit-card-payment').then(() => boot());
