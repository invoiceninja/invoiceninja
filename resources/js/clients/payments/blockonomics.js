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
        this.copyToClipboard = this.copyToClipboard.bind(this);
        this.refreshBTCPrice = this.refreshBTCPrice.bind(this);
        this.fetchAndDisplayQRCode = this.fetchAndDisplayQRCode.bind(this);
        this.startTimer = this.startTimer.bind(this);
    }

    copyToClipboard(elementId, passedElement, shouldGrabNextElementSibling) {
        const element = shouldGrabNextElementSibling ? passedElement.nextElementSibling : passedElement;
        const originalIcon = element.src;

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

        setTimeout(() => {
            element.src = originalIcon;
        }, 5000);
    }

    async loadQRCodeScript() {
        if (window.QRCode) return; // already loaded

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = "https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js";
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async fetchAndDisplayQRCode(newBtcAmount = null) {
        try {
            await this.loadQRCodeScript();

            const btcAddress = document.querySelector('meta[name="btc_address"]').content;
            const btcAmount = newBtcAmount || document.querySelector('meta[name="btc_amount"]').content;
            const qrString = `bitcoin:${btcAddress}?amount=${btcAmount}`;

            document.getElementById('qrcode-container').innerHTML = "";

            new QRCode(document.getElementById("qrcode-container"), {
                text: qrString,
                width: 150,
                height: 150,
                correctLevel: QRCode.CorrectLevel.H
            });
        } catch (error) {
            console.error('Error generating QR code:', error);
            document.getElementById('qrcode-container').textContent = 'Error loading QR code';
        }
    }

    startTimer = (seconds) => {
        const countDownDate = new Date().getTime() + seconds * 1000;
        document.getElementById("countdown").innerHTML = "10:00 min";

        const updateCountdown = () => {
            const now = new Date().getTime();
            const distance = countDownDate - now;

            const isRefreshing = document.getElementsByClassName("btc-value")[0].innerHTML.includes("Refreshing");
            if (isRefreshing) return;

            if (distance < 0) {
                this.refreshBTCPrice();
                return;
            }

            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            const formattedMinutes = String(minutes).padStart(2, '0');
            const formattedSeconds = String(seconds).padStart(2, '0');
            document.getElementById("countdown").innerHTML = `${formattedMinutes}:${formattedSeconds} min`;
        };

        clearInterval(window.countdownInterval);
        window.countdownInterval = setInterval(updateCountdown, 1000);
    }

    async refreshBTCPrice() {
        const refreshIcon = document.querySelector('.icon-refresh');
        refreshIcon.classList.add('rotating');
        document.getElementsByClassName("btc-value")[0].innerHTML = "Refreshing...";

        const getBTCPrice = async () => {
            try {
                const currency = document.querySelector('meta[name="currency"]').content;
                const response = await fetch(`/api/v1/get-btc-price?currency=${currency}`); // New endpoint to call server-side function

                if (!response.ok) throw new Error('Network response was not ok');
                const data = await response.json();
                console.log("BTC price data:", data);
                return data.price;
            } catch (error) {
                console.error('There was a problem with the BTC price fetch operation:', error);
                return null;
            }
        };

        try {
            const newPrice = await getBTCPrice();
            if (newPrice) {
                const currency = document.querySelector('meta[name="currency"]').content;
                document.getElementsByClassName("btc-value")[0].innerHTML =
                    `1 BTC = ${newPrice || "N/A"} ${currency}, updates in <span id='countdown'></span>`;
                const newBtcAmount = (document.querySelector('meta[name="amount"]').content / newPrice).toFixed(10);

                document.querySelector('input[name="btc_price"]').value = newPrice;
                document.querySelector('input[name="btc_amount"]').value = newBtcAmount;
                document.getElementById('btc-amount').textContent = newBtcAmount;

                const btcAddress = document.querySelector('meta[name="btc_address"]').content;
                document.getElementById('qr-code-link').href = `bitcoin:${btcAddress}?amount=${newBtcAmount}`;
                document.getElementById('open-in-wallet-link').href = `bitcoin:${btcAddress}?amount=${newBtcAmount}`;

                await this.fetchAndDisplayQRCode(newBtcAmount);
                this.startTimer(600);
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
                if ([0, 1, 2].includes(status)) {
                    document.querySelector('input[name="txid"]').value = txid || '';
                    document.querySelector('input[name="status"]').value = status || '';
                    document.querySelector('input[name="btc_amount"]').value = value || '';
                    document.querySelector('input[name="btc_address"]').value = btcAddress || '';
                    document.getElementById('server-response').submit();
                }
            };
        };

        this.startTimer(600);
        connectToWebsocket();
        this.fetchAndDisplayQRCode();
    }
}

function boot() {
    new Blockonomics().handle();
    window.bootBlockonomics = boot;
}

instant() ? boot() : wait('#blockonomics-payment').then(() => boot());
