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
        this.form = { valid: false };

        this.translations = {
            invalidCard: document.querySelector('meta[name="credit-card-invalid"]').content,
            invalidMonth: document.querySelector('meta[name="month-invalid"]').content,
            invalidYear: document.querySelector('meta[name="year-invalid"]').content,
        }
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

    handleFormValidation = () => {
        document.getElementById("card_number").addEventListener('keyup', (e) => {
            let errors = document.getElementById('card_number_errors');
            if (valid.number(e.target.value).isValid) {
                errors.hidden = true;
                this.form.valid = true;
            } else {
                errors.textContent = this.translations.invalidCard;
                errors.hidden = false;
                this.form.valid = false;
            }
        });

        document.getElementById("expiration_month").addEventListener('keyup', (e) => {
            let errors = document.getElementById('expiration_month_errors');
            if (valid.expirationMonth(e.target.value).isValid) {
                errors.hidden = true;
                this.form.valid = true;
            } else {
                errors.textContent = this.translations.invalidMonth;
                errors.hidden = false;
                this.form.valid = false;
            }
        });

        document.getElementById("expiration_year").addEventListener('keyup', (e) => {
            let errors = document.getElementById('expiration_year_errors');
            if (valid.expirationYear(e.target.value).isValid) {
                errors.hidden = true;
                this.form.valid = true;
            } else {
                errors.textContent = this.translations.invalidYear;
                errors.hidden = false;
                this.form.valid = false;
            }
        });
    }

    handle() {
        this.handleFormValidation();

        // At this point as an small API you can request this.form.valid to check if input elements are valid.
        // Note: this.form.valid will not handle empty fields.

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