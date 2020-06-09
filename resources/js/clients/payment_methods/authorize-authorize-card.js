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

    	var authData = {};
        authData.clientKey = this.publicKey;
        authData.apiLoginID = this.loginId;
    
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

		Accept.dispatchData(secureData, this.responseHandler);
          return false;

    }

    responseHandler(response) {
console.log("responseHandler");
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
            console.log("else");
            // return this.paymentFormUpdate(response.opaqueData);
            // 
            document.getElementById("dataDescriptor").value = opaqueData.dataDescriptor;
            document.getElementById("dataValue").value = opaqueData.dataValue;
            document.getElementById("server_response").submit();
	    }

        return false;
	}

	paymentFormUpdate(opaqueData) {
        console.log("payment form update");
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

const loginId = document.querySelector(
    'meta[name="authorize-login-id"]'
).content;

/** @handle */
new AuthorizeAuthorizeCard(publicKey, loginId).handle();