/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class AuthorizeAuthorizeCard {

    constructor(publicKey, loginId) {
        this.publicKey = publicKey;
        this.loginId = loginId;
        this.cardHolderName = document.getElementById("cardholder_name");
        this.cardButton = document.getElementById("card_button");
    }

    handleAuthorization() {
        
        var myCard = $('#my-card');

        var authData = {};
        authData.clientKey = this.publicKey;
        authData.apiLoginID = this.loginId;
    
        var cardData = {};
        cardData.cardNumber = myCard.CardJs('cardNumber');
        cardData.month = myCard.CardJs('expiryMonth');
        cardData.year = myCard.CardJs('expiryYear');;
        cardData.cardCode = document.getElementById("cvv").value;

        var secureData = {};
        secureData.authData = authData;
        secureData.cardData = cardData;

        processingOverlay(true);

		Accept.dispatchData(secureData, this.responseHandler);
          return false;

    }

    responseHandler(response) {
        processingOverlay(false);

	    if (response.messages.resultCode === "Error") {
	        var i = 0;
	        while (i < response.messages.message.length) {
	            console.log(
	                response.messages.message[i].code + ": " +
	                response.messages.message[i].text
	            );
	            i = i + 1;
	        }
	    }
	    else if(response.messages.resultCode === "Ok"){
            
            document.getElementById("dataDescriptor").value = response.opaqueData.dataDescriptor;
            document.getElementById("dataValue").value = response.opaqueData.dataValue;
            document.getElementById("server_response").submit();
	    }

        return false;
	}

    handle() {
        //this.handleFormValidation();

        // At this point as an small API you can request this.form.valid to check if input elements are valid.
        // Note: this.form.valid will not handle empty fields.

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

/** @handle */
new AuthorizeAuthorizeCard(publicKey, loginId).handle();