/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

class HelcimAuthorization {
    constructor() {
        this.apiToken = document.querySelector('meta[name="helcim-token"]')?.content;
        this.testMode = document.querySelector('meta[name="helcim-test-mode"]')?.content === 'true';
        this.isAuthorization = window.location.href.includes('payment_methods/');
    }

    handleAuthorization() {
        const authorizeButton = document.getElementById('authorize-card');

        if (!authorizeButton) {
            return;
        }

        authorizeButton.addEventListener('click', (e) => {
            e.preventDefault();

            const cardholderName = document.querySelector('input[name="cardholder_name"]')?.value || '';
            
            this.processAuthorization(cardholderName);
        });
    }

    processAuthorization(cardholderName) {
        document.getElementById('authorize-card').disabled = true;

        // Use HelcimPay.js to tokenize the card
        helcimPay.createCardToken({
            token: this.apiToken,
            cardholderName: cardholderName,
        }).then((result) => {
            if (result.cardToken) {
                const response = {
                    cardToken: result.cardToken,
                    cardNumber: result.cardNumber,
                    cardExpiry: result.cardExpiry,
                    cardType: result.cardType,
                };

                document.getElementById('gateway_response').value = JSON.stringify(response);
                document.getElementById('is_default').value = document.getElementById('proxy_is_default')?.checked ? '1' : '0';
                document.getElementById('server_response').submit();
            } else {
                this.handleError(result.error || 'Failed to tokenize card');
            }
        }).catch((error) => {
            this.handleError(error.message || 'An error occurred');
        });
    }

    handleError(message) {
        document.getElementById('authorize-card').disabled = false;
        const errorsDiv = document.getElementById('errors');
        errorsDiv.textContent = message;
        errorsDiv.hidden = false;

        setTimeout(() => {
            errorsDiv.hidden = true;
        }, 5000);
    }
}

class HelcimPayment {
    constructor() {
        this.apiToken = document.querySelector('meta[name="helcim-token"]')?.content;
        this.testMode = document.querySelector('meta[name="helcim-test-mode"]')?.content === 'true';
        this.amount = parseFloat(document.querySelector('meta[name="amount"]')?.content || 0);
        this.currency = document.querySelector('meta[name="currency"]')?.content || 'CAD';
        this.payingWithToken = false;
        this.selectedTokenId = null;
    }

    handle() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        const payNowButton = document.getElementById('pay-now');

        if (!payNowButton) {
            return;
        }

        // Handle token selection
        document.querySelectorAll('.toggle-payment-with-token').forEach((element) => {
            element.addEventListener('click', (e) => {
                this.payingWithToken = true;
                this.selectedTokenId = e.target.dataset.token;
                document.getElementById('helcim-card-container').style.display = 'none';
                document.getElementById('save-card--container')?.style.display = 'none';
            });
        });

        // Handle new card selection
        const newCardToggle = document.getElementById('toggle-payment-with-credit-card');
        if (newCardToggle) {
            newCardToggle.addEventListener('click', () => {
                this.payingWithToken = false;
                this.selectedTokenId = null;
                document.getElementById('helcim-card-container').style.display = 'block';
                document.getElementById('save-card--container')?.style.display = 'grid';
            });
        }

        payNowButton.addEventListener('click', (e) => {
            e.preventDefault();

            if (this.payingWithToken) {
                this.processTokenPayment();
            } else {
                this.processCardPayment();
            }
        });
    }

    processTokenPayment() {
        document.getElementById('pay-now').disabled = true;

        // Get save card preference
        const saveCardCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');
        const storeCard = saveCardCheckbox ? saveCardCheckbox.value : 'false';

        document.getElementById('store_card').value = storeCard;
        document.getElementById('token').value = this.selectedTokenId;

        // For token payments, we need to charge on the backend
        // Submit a minimal gateway response indicating token usage
        const response = {
            useToken: true,
            tokenId: this.selectedTokenId,
        };

        document.getElementById('gateway_response').value = JSON.stringify(response);
        document.getElementById('server_response').submit();
    }

    processCardPayment() {
        document.getElementById('pay-now').disabled = true;

        // Get cardholder name and save card preference
        const cardholderName = document.querySelector('input[name="cardholder_name"]')?.value || '';
        const saveCardCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');
        const storeCard = saveCardCheckbox ? saveCardCheckbox.value : 'false';

        // Initialize HelcimPay
        helcimPay.createTransaction({
            token: this.apiToken,
            amount: this.amount,
            currency: this.currency,
            cardholderName: cardholderName,
        }).then((result) => {
            if (result.transactionId) {
                const response = {
                    transactionId: result.transactionId,
                    status: result.status || 'APPROVED',
                    cardToken: result.cardToken,
                    cardNumber: result.cardNumber,
                    cardExpiry: result.cardExpiry,
                    cardType: result.cardType,
                    message: result.message || 'Payment successful',
                };

                document.getElementById('gateway_response').value = JSON.stringify(response);
                document.getElementById('store_card').value = storeCard;
                document.getElementById('server_response').submit();
            } else {
                this.handleError(result.error || 'Payment failed');
            }
        }).catch((error) => {
            this.handleError(error.message || 'An error occurred');
        });
    }

    handleError(message) {
        document.getElementById('pay-now').disabled = false;
        const errorsDiv = document.getElementById('errors');
        errorsDiv.textContent = message;
        errorsDiv.hidden = false;

        setTimeout(() => {
            errorsDiv.hidden = true;
        }, 5000);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.href.includes('payment_methods/')) {
        new HelcimAuthorization().handleAuthorization();
    } else {
        new HelcimPayment().handle();
    }
});