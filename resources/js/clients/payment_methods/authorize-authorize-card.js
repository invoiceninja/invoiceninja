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
        this.cardButton = document.getElementById("card_button");
    }

    handleAuthorization() {


        if (cvvRequired == "1" && document.getElementById("cvv").value.length < 3) {

            var $errors = $('#errors');
            $errors.show().html("<p>CVV is required</p>");

            document.getElementById('card_button').disabled = false;
            document.querySelector('#card_button > svg').classList.add('hidden');
            document.querySelector('#card_button > span').classList.remove('hidden');

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
        cardData.cardCode = document.getElementById("cvv").value.replace(/[^\d]/g, '');;

        var secureData = {};
        secureData.authData = authData;
        secureData.cardData = cardData;

        document.getElementById('card_button').disabled = true;
        document.querySelector('#card_button > svg').classList.remove('hidden');
        document.querySelector('#card_button > span').classList.add('hidden');

		Accept.dispatchData(secureData, this.responseHandler);
          return false;

    }

    responseHandler(response) {
	    if (response.messages.resultCode === "Error") {
	        var i = 0;

            var $errors = $('#errors'); // get the reference of the div
            $errors.show().html("<p>" + response.messages.message[i].code + ": " + response.messages.message[i].text + "</p>");

            document.getElementById('card_button').disabled = false;
            document.querySelector('#card_button > svg').classList.add('hidden');
            document.querySelector('#card_button > span').classList.remove('hidden');
	    }
	    else if(response.messages.resultCode === "Ok"){

            document.getElementById("dataDescriptor").value = response.opaqueData.dataDescriptor;
            document.getElementById("dataValue").value = response.opaqueData.dataValue;
            document.getElementById("server_response").submit();
	    }

        return false;
	}

    handle() {
        this.cardButton.addEventListener("click", () => {
            this.cardButton.disabled = !this.cardButton.disabled;
             this.handleAuthorization();
        });


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
