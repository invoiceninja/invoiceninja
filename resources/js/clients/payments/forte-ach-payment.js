/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class ForteAuthorizeACH {
    constructor(apiLoginId) {
        this.apiLoginId = apiLoginId;
    }

    handleAuthorization = () => {
        var account_number = document.getElementById('account-number').value;
        var routing_number = document.getElementById('routing-number').value;

        var data = {
            api_login_id: this.apiLoginId,
            account_number: account_number,
            routing_number: routing_number,
            account_type: 'checking',
        };

        let payNowButton = document.getElementById('pay-now');

        if (payNowButton) {
            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.remove('hidden');
            document.querySelector('#pay-now > span').classList.add('hidden');
        }
        // console.log(data);
        forte
            .createToken(data)
            .success(this.successResponseHandler)
            .error(this.failedResponseHandler);
        return false;
    };

    successResponseHandler = (response) => {
        document.getElementById('payment_token').value = response.onetime_token;

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

const apiLoginId = document.querySelector(
    'meta[name="forte-api-login-id"]'
).content;

/** @handle */
new ForteAuthorizeACH(apiLoginId).handle();
