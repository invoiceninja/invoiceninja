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
    constructor(key) {
        this.key = key;
        this.cardHolderName = document.getElementById("cardholder_name");
        this.cardButton = document.getElementById("card_button");

    }

    handleAuthorization() {

    	var authData = {};
        authData.clientKey = this.key;
        authData.apiLoginID = "YOUR API LOGIN ID";
    
    	var cardData = {};
        cardData.cardNumber = document.getElementById("card_number").value;
        cardData.month = document.getElementById("expiration_month").value;
        cardData.year = document.getElementById("expiration_year").value;
        cardData.cardCode = document.getElementById("cvv").value;

    	var secureData = {};
        secureData.authData = authData;
        secureData.cardData = cardData;
        // If using banking information instead of card information,
        // send the bankData object instead of the cardData object.
        //
        // secureData.bankData = bankData;

		Accept.dispatchData(secureData, responseHandler);
    }

    responseHandler(response) {

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
	    else {
        	paymentFormUpdate(response.opaqueData);
	    }

	}

	paymentFormUpdate(opaqueData) {
	    document.getElementById("dataDescriptor").value = opaqueData.dataDescriptor;
	    document.getElementById("dataValue").value = opaqueData.dataValue;
        document.getElementById("server_response").submit();
	}

    handle() {

        this.cardButton.addEventListener("click", () => {
            this.handleAuthorization();
        });

        return this;
    }
}

const publicKey = document.querySelector(
    'meta[name="authorize-public-key"]'
).content;

/** @handle */
new AuthorizeAuthorizeCard(publicKey).handle();