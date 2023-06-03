/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

class AuthorizeAuthorizeCard {

    constructor(publicKey, loginId) {
        this.publicKey = publicKey;
        this.loginId = loginId;
        this.cardHolderName = document.getElementById("cardholder_name");
    }

    handleAuthorization = () => {

        if (cvvRequired == "1" && document.getElementById("cvv").value.length < 3) {
            var $errors = $('#errors');
            $errors.show().html("<p>CVV is required</p>");

            document.getElementById('pay-now').disabled = false;
            document.querySelector('#pay-now > svg').classList.add('hidden');
            document.querySelector('#pay-now > span').classList.remove('hidden');
            return;
        }

        var myCard = $('#my-card');

        var authData = {};
        authData.clientKey = this.publicKey;
        authData.apiLoginID = this.loginId;

        var cardData = {};
        cardData.cardNumber = myCard.CardJs('cardNumber').replace(/[^\d]/g, '');
        cardData.month = myCard.CardJs('expiryMonth').replace(/[^\d]/g, '');
        cardData.year = myCard.CardJs('expiryYear').replace(/[^\d]/g, '');
        cardData.cardCode = document.getElementById("cvv").value.replace(/[^\d]/g, '');

        var secureData = {};
        secureData.authData = authData;
        secureData.cardData = cardData;
        // If using banking information instead of card information,
        // send the bankData object instead of the cardData object.
        //
        // secureData.bankData = bankData;
        let payNowButton = document.getElementById('pay-now');

        if (payNowButton) {
            document.getElementById('pay-now').disabled = true;
            document.querySelector('#pay-now > svg').classList.remove('hidden');
            document.querySelector('#pay-now > span').classList.add('hidden');
        }

        Accept.dispatchData(secureData, this.responseHandler);

        return false;

    }

    handlePayNowAction(token_hashed_id) {
        document.getElementById('pay-now').disabled = true;
        document.querySelector('#pay-now > svg').classList.remove('hidden');
        document.querySelector('#pay-now > span').classList.add('hidden');

        document.getElementById("token").value = token_hashed_id;
        document.getElementById("server_response").submit();
    }

    responseHandler = (response) => {
        if (response.messages.resultCode === "Error") {
            var i = 0;

            var $errors = $('#errors'); // get the reference of the div
            $errors.show().html("<p>" + response.messages.message[i].code + ": " + response.messages.message[i].text + "</p>");

            document.getElementById('pay-now').disabled = false;
            document.querySelector('#pay-now > svg').classList.add('hidden');
            document.querySelector('#pay-now > span').classList.remove('hidden');
        } else if (response.messages.resultCode === "Ok") {

            document.getElementById("dataDescriptor").value = response.opaqueData.dataDescriptor;
            document.getElementById("dataValue").value = response.opaqueData.dataValue;

            let storeCard = document.querySelector('input[name=token-billing-checkbox]:checked');

            if (storeCard) {
                document.getElementById("store_card").value = storeCard.value;
            }

            document.getElementById("server_response").submit();
        }

        return false;
    }


    handle = () => {
        Array
            .from(document.getElementsByClassName('toggle-payment-with-token'))
            .forEach((element) => element.addEventListener('click', (e) => {
                document
                    .getElementById('save-card--container').style.display = 'none';
                document
                    .getElementById('authorize--credit-card-container').style.display = 'none';

                document
                    .getElementById('token').value = e.target.dataset.token;
            }));

        let payWithCreditCardToggle = document.getElementById('toggle-payment-with-credit-card');

        if (payWithCreditCardToggle) {
            payWithCreditCardToggle
                .addEventListener('click', () => {
                    document
                        .getElementById('save-card--container').style.display = 'grid';
                    document
                        .getElementById('authorize--credit-card-container').style.display = 'flex';

                    document
                        .getElementById('token').value = null;
                });
        }

        let payNowButton = document.getElementById('pay-now');

        if (payNowButton) {
            payNowButton
                .addEventListener('click', (e) => {
                    let token = document.getElementById('token');

                    token.value
                        ? this.handlePayNowAction(token.value)
                        : this.handleAuthorization();
                });
        }

        return this;
    }
}

const publicKey = document.querySelector(
    'meta[name="authorize-public-key"]'
).content;

const loginId = document.querySelector(
    'meta[name="authorize-login-id"]'
).content;

const cvvRequired = document.querySelector(
    'meta[name="authnet-require-cvv"]'
).content;

/** @handle */
new AuthorizeAuthorizeCard(publicKey, loginId).handle();
