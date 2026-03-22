/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

class Blockonomics {

    constructor() {
        // Bind the method to the instance
        this.copyToClipboard = this.copyToClipboard.bind(this);
        this.refreshBTCPrice = this.refreshBTCPrice.bind(this);
        this.fetchAndDisplayQRCode = this.fetchAndDisplayQRCode.bind(this);
        this.startTimer = this.startTimer.bind(this);
    }

     copyToClipboard(elementId, passedElement, shouldGrabNextElementSibling) {

        const element = shouldGrabNextElementSibling ? passedElement.nextElementSibling : passedElement;
        const originalIcon = element.src;  // Store the original icon

        const tempInput = document.createElement("input");
        const elementWithId = document.getElementById(elementId);
        const { value, innerText } = elementWithId || {};
        const text = value || innerText;

        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);

        element.src = 'data:image/svg+xml;base64,' + btoa(`
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.04706 14C4.04706 8.55609 8.46025 4.1429 13.9042 4.1429C19.3482 4.1429 23.7613 8.55609 23.7613 14C23.7613 19.444 19.3482 23.8572 13.9042 23.8572C8.46025 23.8572 4.04706 19.444 4.04706 14Z" stroke="#000" stroke-width="2.19048" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9.52325 14L12.809 17.2858L18.2852 11.8096" stroke="#000" stroke-width="2.19048" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                `);

        // Change the icon back to the original after 5 seconds
        setTimeout(() => {
            element.src = originalIcon;
        }, 5000);
    }


    // QR code fetching is now handled by Livewire component (BlockonomicsQRCode)
    async fetchAndDisplayQRCode () {
        // This method is deprecated - use Livewire component instead
    };

    // Countdown timer is now handled by Livewire component (BlockonomicsPriceDisplay)
    startTimer = () => {
        // This method is deprecated - use Livewire component instead
    }


    async refreshBTCPrice() {
        const refreshIcon = document.querySelector('.icon-refresh');
        refreshIcon.classList.add('rotating');
        document.getElementsByClassName("btc-value")[0].innerHTML = "Refreshing...";

        const getBTCPrice = async () => {
            try {
                const currency = document.querySelector('meta[name="currency"]').content;
                const response = await fetch(`/api/v1/get-btc-price?currency=${currency}`); // New endpoint to call server-side function
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                return data.price;
            } catch (error) {
                console.error('There was a problem with the BTC price fetch operation:', error);
                // Handle error appropriately
            }
        }

        try {
            const newPrice = await getBTCPrice();
            if (newPrice) {
                // Update the text content of the countdown span to the new bitcoin price
                const currency = document.querySelector('meta[name="currency"]').content;
                document.getElementsByClassName("btc-value")[0].innerHTML = "1 BTC = " + (newPrice || "N/A") + " " + currency + ", updates in <span id='countdown'></span>";
                const newBtcAmount = (document.querySelector('meta[name="amount"]').content / newPrice).toFixed(10);

                // set the value of the input field and the text content of the span to the new bitcoin amount
                document.querySelector('input[name="btc_price"]').value = newPrice;
                document.querySelector('input[name="btc_amount"]').value = newBtcAmount;
                document.getElementById('btc-amount').textContent = newBtcAmount;

                const btcAddress = document.querySelector('meta[name="btc_address"]').content;

                // set the href attribute of the link to the new bitcoin amount
                const qrCodeLink = document.getElementById('qr-code-link');
                const openInWalletLink = document.getElementById('open-in-wallet-link');
                qrCodeLink.href = `bitcoin:${btcAddress}?amount=${newBtcAmount}`;
                openInWalletLink.href = `bitcoin:${btcAddress}?amount=${newBtcAmount}`;

                // fetch and display the new QR code
                await this.fetchAndDisplayQRCode(newBtcAmount);
                this.startTimer(600); // Restart timer for 10 minutes (600 seconds)
            }
        } finally {
            refreshIcon.classList.remove('rotating');
        }
    }


    handle() {
        window.copyToClipboard = this.copyToClipboard;
        window.refreshBTCPrice = this.refreshBTCPrice;
        window.fetchAndDisplayQRCode = this.fetchAndDisplayQRCode;
        window.startTimer = this.startTimer;

        const connectToWebsocket = () => {
            const btcAddress = document.querySelector('meta[name="btc_address"]').content;
            const webSocketUrl = `wss://www.blockonomics.co/payment/${btcAddress}`;
            const ws = new WebSocket(webSocketUrl);

            ws.onmessage = function (event) {
                const data = JSON.parse(event.data);
                const { status, txid, value } = data || {};
                console.log('Payment status:', status);
                const isPaymentUnconfirmed = status === 0;
                const isPaymentPartiallyConfirmed = status === 1;
                const isPaymentConfirmed = status === 2;
                // Confirmation status: 0 = unconfirmed, 1 = partially confirmed, 2 = confirmed
                // If any of the statuses are true, submit the form and redirect
                if (isPaymentUnconfirmed || isPaymentPartiallyConfirmed || isPaymentConfirmed) {
                    document.querySelector('input[name="txid"]').value = txid || '';
                    document.querySelector('input[name="status"]').value = status || '';
                    document.querySelector('input[name="btc_amount"]').value = value || '';
                    document.querySelector('input[name="btc_address"]').value = btcAddress || '';
                    document.getElementById('server-response').submit();
                }
            }
        };
        startTimer(600); // Start timer for 10 minutes (600 seconds)
        connectToWebsocket();
        fetchAndDisplayQRCode();
    }

}

function boot() {
    new Blockonomics().handle();
    window.bootBlockonomics = boot;
}

instant() ? boot() : wait('#blockonomics-payment').then(() => boot());
