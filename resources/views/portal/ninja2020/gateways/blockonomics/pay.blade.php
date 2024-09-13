@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_Crypto'), 'card_title' => ctrans('texts.payment_type_Crypto')])

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>
    <div class="blockonomics-payment-wrapper">
        <div class="initial-state">
        <div class="invoice-info-wrapper">
            <div class="invoice-number">Invoice #{{$invoice_number}}</div>
            <div class="invoice-amount">{{$amount}} {{$currency}}</div>
        </div>
        <div class="sections-wrapper">
            <div class="scan-section">
                <div class="title">Scan</div>
                <span class="input-wrapper">
                    <a href="bitcoin:{{$btc_address}}?amount={{$btc_amount}}" id="qr-code-link" target="_blank">
                        <div id="qrcode-container"></div>
                    </a>
                </span>
                <a href="bitcoin:{{$btc_address}}?amount={{$btc_amount}}" target="_blank" id="open-in-wallet-link">Open in Wallet</a>
            </div>
            <div class="copy-section">
                <div class="title">Copy</div>
                <span>To pay, send bitcoin to this address:</span>
                <span class="input-wrapper">
                    <input onclick='copyToClipboard("btc-address", this, true)' class="full-width-input" id="btc-address" value="{{$btc_address}}" readonly>
                    <img onclick='copyToClipboard("btc-address", this)' src="{{ 'data:image/svg+xml;base64,' . base64_encode('<svg width="22" height="24" viewBox="0 0 22 24" fill="none" xmlns="http://www.w3.org/2000/svg" ><path d="M15.5 1H3.5C2.4 1 1.5 1.9 1.5 3V17H3.5V3H15.5V1ZM18.5 5H7.5C6.4 5 5.5 5.9 5.5 7V21C5.5 22.1 6.4 23 7.5 23H18.5C19.6 23 20.5 22.1 20.5 21V7C20.5 5.9 19.6 5 18.5 5ZM18.5 21H7.5V7H18.5V21Z" fill="#000"/></svg>') }}" class="icon" alt="Copy Icon">
                </span>
                <span>Amount of bitcoin (BTC) to send:</span>
                <span class="input-wrapper">
                    <div class="full-width-input" id="btc-amount" onclick='copyToClipboard("btc-amount", this, true)''>
                        {{$btc_amount}}
                    </div>
                    <img onclick='copyToClipboard("btc-amount", this)'' src="{{ 'data:image/svg+xml;base64,' . base64_encode('<svg width="22" height="24" viewBox="0 0 22 24" fill="none" xmlns="http://www.w3.org/2000/svg" ><path d="M15.5 1H3.5C2.4 1 1.5 1.9 1.5 3V17H3.5V3H15.5V1ZM18.5 5H7.5C6.4 5 5.5 5.9 5.5 7V21C5.5 22.1 6.4 23 7.5 23H18.5C19.6 23 20.5 22.1 20.5 21V7C20.5 5.9 19.6 5 18.5 5ZM18.5 21H7.5V7H18.5V21Z" fill="#000"/></svg>') }}" class="icon" alt="Copy Icon">
                </span>
                <div class="btc-value-wrapper">
                    <div class="btc-value">1 BTC = {{$btc_price}} {{$currency}}, updates in <span id="countdown"></span></div>
                    <span class="icon-refresh" onclick='refreshBTCPrice()'></span>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway_id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="token">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="btc_price" value="{{ $btc_price }}">
        <input type="hidden" name="btc_amount" value="{{ $btc_amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="txid" value="">
    </form>

    <script>
        const startTimer = (seconds) => {
            const countDownDate = new Date().getTime() + seconds * 1000;
            document.getElementById("countdown").innerHTML = "10" + ":" + "00" + " min";

            const updateCountdown = () => {
                const now = new Date().getTime();
                const distance = countDownDate - now;

                const isRefreshing = document.getElementsByClassName("btc-value")[0].innerHTML.includes("Refreshing");
                if (isRefreshing) {
                    return;
                }

                if (distance < 0) {
                    refreshBTCPrice();
                    return;
                }

                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                const formattedMinutes = String(minutes).padStart(2, '0');
                const formattedSeconds = String(seconds).padStart(2, '0');
                document.getElementById("countdown").innerHTML = formattedMinutes + ":" + formattedSeconds + " min";
            }

            clearInterval(window.countdownInterval);
            window.countdownInterval = setInterval(updateCountdown, 1000);
        }

        const copyToClipboard = (elementId, passedElement, shouldGrabNextElementSibling) => {
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

        const getBTCPrice = async () => {
            try {
                const response = await fetch(`/api/v1/get-btc-price?currency={{$currency}}`); // New endpoint to call server-side function
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

        const refreshBTCPrice = async () => {
            const refreshIcon = document.querySelector('.icon-refresh');
            refreshIcon.classList.add('rotating');
            document.getElementsByClassName("btc-value")[0].innerHTML = "Refreshing...";

            try {
                const newPrice = await getBTCPrice();
                if (newPrice) {
                    // Update the text content of the countdown span to the new bitcoin price
                    document.getElementsByClassName("btc-value")[0].innerHTML = "1 BTC = " + (newPrice || "N/A") + " {{$currency}}, updates in <span id='countdown'></span>";
                    const newBtcAmount = ({{$amount}} / newPrice).toFixed(10);

                    // set the value of the input field and the text content of the span to the new bitcoin amount
                    document.querySelector('input[name="btc_price"]').value = newPrice;
                    document.querySelector('input[name="btc_amount"]').value = newBtcAmount;
                    document.getElementById('btc-amount').textContent = newBtcAmount;
                    
                    // set the href attribute of the link to the new bitcoin amount
                    const qrCodeLink = document.getElementById('qr-code-link');
                    const openInWalletLink = document.getElementById('open-in-wallet-link');
                    qrCodeLink.href = `bitcoin:{{$btc_address}}?amount=${newBtcAmount}`;
                    openInWalletLink.href = `bitcoin:{{$btc_address}}?amount=${newBtcAmount}`;
                    
                    // fetch and display the new QR code
                    fetchAndDisplayQRCode(newBtcAmount);
                    startTimer(600); // Restart timer for 10 minutes (600 seconds)
                }
            } finally {
                refreshIcon.classList.remove('rotating');
            }
        }

        const connectToWebsocket = () => {
            const webSocketUrl = "wss://www.blockonomics.co/payment/{{ $btc_address }}";
            const ws = new WebSocket(webSocketUrl);

            ws.onmessage = function(event) {
                const data = JSON.parse(event.data);
                console.log('Payment status:', data.status);
                const isPaymentUnconfirmed = data.status === 0;
                const isPaymentPartiallyConfirmed = data.status === 1;
                // TODO: Do we need to handle Payment confirmed status?
                // This usually takes too long to happen, so we can just wait for the unconfirmed status?
                if (isPaymentUnconfirmed || isPaymentPartiallyConfirmed) {
                    document.querySelector('input[name="txid"]').value = data.txid || '';
                    document.getElementById('server-response').submit();
                }
            }
        };

        const fetchAndDisplayQRCode = async (newBtcAmount = null) => {
            try {
                const btcAmount = newBtcAmount || '{{$btc_amount}}';
                const response = await fetch(`/api/v1/get-blockonomics-qr-code?qr_string=bitcoin:${btcAmount}?amount={{$btc_amount}}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const svgText = await response.text();
                document.getElementById('qrcode-container').innerHTML = svgText;
            } catch (error) {
                console.error('Error fetching QR code:', error);
                document.getElementById('qrcode-container').textContent = 'Error loading QR code';
            }
        };

        startTimer(600); // Start timer for 10 minutes (600 seconds)
        connectToWebsocket();
        fetchAndDisplayQRCode();

    </script>


    <style type="text/css">
        .sections-wrapper {
            display: flex;
            flex-direction: row;
            justify-content: space-around;
            /* Mobile devices */
            @media (max-width: 768px) {
                flex-direction: column; /* Change to column on smaller screens */
            }
        }
        .copy-section {
            width: 60%;
            @media (max-width: 768px) {
                width: 100%; /* Full width on smaller screens */
            }
        }
        .title {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        #open-in-wallet-link {
            text-align: center;
            text-decoration: underline;
            width: 100%;
            justify-content: center;
            display: flex;
            margin-top: 10px;
            margin-bottom: 20px;
             &:hover {
                text-decoration: none;
            }
        }
        .invoice-info-wrapper {
            width: 100%;
            text-transform: uppercase;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }    
        .invoice-number {
            width: 50%;
            float: left;
            text-align: left;
        }    
        .invoice-amount {
            width: 50%;
            float: right;
            text-align: right;
            text-transform: uppercase;
            margin-bottom: 20px;
        }    
        .blockonomics-payment-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .initial-state {
            justify-content: center;
            display: flex;
            flex-direction: column;
            width: 100%;
            padding: 24px;
        }
        .input-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: row;
            width: 100%;
            margin-bottom: 10px;
        }
        .full-width-input {
            width: 100%;
            max-width: 400px;
            padding: 10px;
            text-align: left;
            border: 1px solid #ccc;
            border-radius: 5px;
            color: #444;
            cursor: pointer;
            position: relative;
        }
        .icon {
            cursor: pointer;
            width: 28px;
            margin-left: 5px;
        }
        .icon-refresh::before {
            content: '\27F3';
            cursor: pointer;
            margin-left: 5px;
            width: 28px;
            display: flex;
            font-size: 32px;
            margin-bottom: 5px;
        }
        .btc-value {
            font-size: 14px;
            text-align: center;
        }
        .btc-value-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: row;
        }
        @keyframes rotating {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        .rotating {
            animation: rotating 2s linear infinite;
        }
    </style>
@endsection

