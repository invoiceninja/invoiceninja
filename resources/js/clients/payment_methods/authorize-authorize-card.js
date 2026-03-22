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

        this.sc = createSimpleCard({
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

    handleAuthorization() {
        if (
            this.cvvRequired == '1' &&
            document.getElementById('cvv').value.length < 3
        ) {
            const $errors = document.getElementById('errors');
            
            if ($errors) {
                $errors.innerText = 'CVV is required';
                $errors.style.display = 'block';
            }

            document.getElementById('pay-now').disabled = false;
            document.querySelector('#pay-now > svg').classList.add('hidden');
            document
                .querySelector('#pay-now > span')
                .classList.remove('hidden');
            return;
        }

        var authData = {};
        authData.clientKey = this.publicKey;
        authData.apiLoginID = this.loginId;

        var cardData = {};
        cardData.cardNumber = this.sc.value('number')?.replace(/[^\d]/g, '');
        cardData.month = this.sc.value('month')?.replace(/[^\d]/g, '');
        cardData.year = `20${this.sc.value('year')?.replace(/[^\d]/g, '')}`;
        cardData.cardCode = this.sc.value('cvv')?.replace(/[^\d]/g, '');

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

            const $errors = document.getElementById('errors'); // get the reference of the div

            if ($errors) {
                $errors.innerText = `${response.messages.message[i].code}: ${response.messages.message[i].text}`;
                $errors.style.display = 'block';
            }

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
