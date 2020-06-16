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
        this.payNowButton = document.getElementsByClassName("pay_now_button");
    }

    handleAuthorization = () => {

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

    handlePayNowAction(token_hashed_id) {

        console.log(token_hashed_id);

        document.getElementById("token").value = token_hashed_id;
        document.getElementById("server_response").submit();

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
	    else if(response.messages.resultCode === "Ok"){
            
            document.getElementById("dataDescriptor").value = response.opaqueData.dataDescriptor;
            document.getElementById("dataValue").value = response.opaqueData.dataValue;
            document.getElementById("server_response").submit();

	    }

        return false;
	}



    handle = () => {


        console.log(this.payNowButton);

        if(this.cardButton)
        {
            this.cardButton.addEventListener("click", () => {

                this.handleAuthorization();

            });
        }

        if(this.payNowButton)
        {

            for(let item of this.payNowButton) {

                item.addEventListener('click', () => {
                    
                    this.handlePayNowAction(item.dataset.id);

                });

            }

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

/** @handle */
new AuthorizeAuthorizeCard(publicKey, loginId).handle();