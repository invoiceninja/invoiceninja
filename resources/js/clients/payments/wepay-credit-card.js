/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

let action = document.querySelector('meta[name="wepay-action"]')?.content;

class WePayCreditCard {
    constructor(action = 'payment') {
        this.action = action;
        this.errors = document.getElementById('errors');
    }

    initializeWePay() {
        let environment = document.querySelector('meta[name="wepay-environment"]')?.content;

        WePay.set_endpoint(environment === 'staging' ? 'stage' : 'production');

        return this;
    }

    validateCreditCardFields() {
        this.myCard = $('#my-card');

        if (document.getElementById('cardholder_name') === "") {
            document.getElementById('cardholder_name').focus();
            this.errors.textContent = "Cardholder name required.";
            this.errors.hidden = false;

            return;
        } else if (this.myCard.CardJs('cardNumber').replace(/[^\d]/g, '') === "") {
            document.getElementById('card_number').focus();
            this.errors.textContent = "Card number required.";
            this.errors.hidden = false;

            return;
        } else if (this.myCard.CardJs('cvc').replace(/[^\d]/g, '') === "") {
            document.getElementById('cvv').focus();
            this.errors.textContent = "CVV number required.";
            this.errors.hidden = false;

            return;
        } else if (this.myCard.CardJs('expiryMonth').replace(/[^\d]/g, '') === "") {
            // document.getElementById('expiry_month').focus();
            this.errors.textContent = "Expiry Month number required.";
            this.errors.hidden = false;

            return;
        } else if (this.myCard.CardJs('expiryYear').replace(/[^\d]/g, '') === "") {
            // document.getElementById('expiry_year').focus();
            this.errors.textContent = "Expiry Year number required.";
            this.errors.hidden = false;

            return;
        }

        return true;
    }

    handleAuthorization() {
        if (!this.validateCreditCardFields()) {
            return;
        }

        let cardButton = document.getElementById('card_button');
        cardButton.disabled = true;

        cardButton.querySelector('svg').classList.remove('hidden');
        cardButton.querySelector('span').classList.add('hidden');

        WePay.credit_card.create({
            client_id: document.querySelector('meta[name=wepay-client-id]').content,
            user_name: document.getElementById('cardholder_name').value,
            email: document.querySelector('meta[name=contact-email]').content,
            cc_number: this.myCard.CardJs('cardNumber').replace(/[^\d]/g, ''),
            cvv: this.myCard.CardJs('cvc').replace(/[^\d]/g, ''),
            expiration_month: this.myCard.CardJs('expiryMonth').replace(/[^\d]/g, ''),
            expiration_year: this.myCard.CardJs('expiryYear').replace(/[^\d]/g, ''),
            address: {
                country: document.querySelector(['meta[name=country_code']).content,
                postal_code: document.querySelector(['meta[name=client-postal-code']).content,
            }
        }, (data) => {
            if (data.error) {
                cardButton = document.getElementById('card_button');
                cardButton.disabled = false;
                cardButton.querySelector('svg').classList.add('hidden');
                cardButton.querySelector('span').classList.remove('hidden');

                this.errors.textContent = '';
                this.errors.textContent = data.error_description;
                this.errors.hidden = false;
            } else {
                document.querySelector('input[name="credit_card_id"]').value = data.credit_card_id;
                document.getElementById('server_response').submit();
            }
        })
    }

    completePaymentUsingToken(token) {
        document.querySelector('input[name="credit_card_id"]').value = null;
        document.querySelector('input[name="token"]').value = token;
        document.getElementById('server-response').submit();
    }

    completePaymentWithoutToken() {
        if (!this.validateCreditCardFields()) {
            
            this.payNowButton = document.getElementById('pay-now');
            this.payNowButton.disabled = false;
            this.payNowButton.querySelector('svg').classList.add('hidden');
            this.payNowButton.querySelector('span').classList.remove('hidden');

            return;
        }

        WePay.credit_card.create({
            client_id: document.querySelector('meta[name=wepay-client-id]').content,
            user_name: document.getElementById('cardholder_name').value,
            email: document.querySelector('meta[name=contact-email]').content,
            cc_number: this.myCard.CardJs('cardNumber').replace(/[^\d]/g, ''),
            cvv: this.myCard.CardJs('cvc').replace(/[^\d]/g, ''),
            expiration_month: this.myCard.CardJs('expiryMonth').replace(/[^\d]/g, ''),
            expiration_year: this.myCard.CardJs('expiryYear').replace(/[^\d]/g, ''),
            address: {
                country: document.querySelector(['meta[name=country_code']).content,
                postal_code: document.querySelector(['meta[name=client-postal-code']).content,
            }
        }, (data) => {
            if (data.error) {
                this.payNowButton.disabled = false;
                this.payNowButton.querySelector('svg').classList.add('hidden');
                this.payNowButton.querySelector('span').classList.remove('hidden');

                this.errors.textContent = '';
                this.errors.textContent = data.error_description;
                this.errors.hidden = false;
            } else {
                document.querySelector('input[name="credit_card_id"]').value = data.credit_card_id;
                document.querySelector('input[name="token"]').value = null;
                document.getElementById('server-response').submit();
            }
        })
    }

    handle() {
        this.initializeWePay();

        if (this.action === 'authorize') {
            document
                .getElementById('card_button')
                .addEventListener('click', () => this.handleAuthorization());
        } else if (this.action === 'payment') {
            Array
                .from(document.getElementsByClassName('toggle-payment-with-token'))
                .forEach((element) => element.addEventListener('click', (e) => {
                    document.getElementById('save-card--container').style.display = 'none';
                    document.getElementById('wepay--credit-card-container').style.display = 'none';
                    document.getElementById('token').value = e.target.dataset.token;
                }));

            document
                .getElementById('toggle-payment-with-credit-card')
                .addEventListener('click', (e) => {
                    document.getElementById('save-card--container').style.display = 'grid';
                    document.getElementById('wepay--credit-card-container').style.display = 'flex';
                    document.getElementById('token').value = null;
                });

            document
                .getElementById('pay-now')
                .addEventListener('click', () => {
                    this.payNowButton = document.getElementById('pay-now');
                    this.payNowButton.disabled = true;
                    this.payNowButton.querySelector('svg').classList.remove('hidden');
                    this.payNowButton.querySelector('span').classList.add('hidden');

                    let tokenInput = document.querySelector('input[name=token]');

                    let storeCard = document.querySelector('input[name=token-billing-checkbox]:checked');

                    if (storeCard) {
                        document.getElementById("store_card").value = storeCard.value;
                    }

                    if (tokenInput.value) {
                        return this.completePaymentUsingToken(tokenInput.value);
                    }

                    return this.completePaymentWithoutToken();
                });
        }
    }
}

new WePayCreditCard(action).handle();
