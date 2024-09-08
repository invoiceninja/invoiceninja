@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_Crypto'), 'card_title' => ctrans('texts.payment_type_Crypto')])

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div class="blockonomics-payment-wrapper">
        <div class="progress-message">
            Your payment txid has been recieved.
            <!-- <span id="txid"></span>  -->
            <br/><br/>
            The <a id="link" href="{{ $invoice_redirect_url }}" target="_blank">invoice</a> will be marked as paid automatically once the payment is confirmed.
        </div>
        <div class="initial-state">
        <div class="invoice-number">Invoice #{{$invoice_number}}</div>
        <div>To pay, send exactly this BTC amount</div>
        <div class="full-width-input" onclick='copyToClipboard("{{$btc_amount}}", this)'>
            {{$btc_amount}} BTC <span style="color: gray;">≈ {{$amount}} {{$currency}}</span>
            <img src="{{ 'data:image/svg+xml;base64,' . base64_encode('<svg width="22" height="24" viewBox="0 0 22 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 1H3.5C2.4 1 1.5 1.9 1.5 3V17H3.5V3H15.5V1ZM18.5 5H7.5C6.4 5 5.5 5.9 5.5 7V21C5.5 22.1 6.4 23 7.5 23H18.5C19.6 23 20.5 22.1 20.5 21V7C20.5 5.9 19.6 5 18.5 5ZM18.5 21H7.5V7H18.5V21Z" fill="#000"/></svg>') }}" class="icon" alt="Copy Icon">
        </div>
        <div>To this bitcoin address</div>
        <div class="input-wrapper" onclick='copyToClipboard("{{$btc_address}}", this)'>
            <input class="full-width-input" id="btcAddress" value="{{$btc_address}}" readonly >
            <img src="{{ 'data:image/svg+xml;base64,' . base64_encode('<svg width="22" height="24" viewBox="0 0 22 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 1H3.5C2.4 1 1.5 1.9 1.5 3V17H3.5V3H15.5V1ZM18.5 5H7.5C6.4 5 5.5 5.9 5.5 7V21C5.5 22.1 6.4 23 7.5 23H18.5C19.6 23 20.5 22.1 20.5 21V7C20.5 5.9 19.6 5 18.5 5ZM18.5 21H7.5V7H18.5V21Z" fill="#000"/></svg>') }}" class="icon" alt="Copy Icon">
        </div>
        <div id="countdown"></div>
        </div>
    </div>

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="token">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="txid" value="">
    </form>

    <script>
        // Get the end time as a Unix timestamp (seconds)
        var endTimeUnix = {{ $end_time }};
        console.log("End time (Unix timestamp):", endTimeUnix); // For debugging

        // Convert Unix timestamp to milliseconds for JavaScript Date
        var countDownDate = endTimeUnix * 1000;

        function updateCountdown() {
            var now = new Date().getTime();
            var distance = countDownDate - now;

            if (distance < 0) {
                document.getElementById("countdown").innerHTML = "EXPIRED";
                return;
            }

            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("countdown").innerHTML = minutes + "m " + seconds + "s ";
        }
        setInterval(updateCountdown, 1000);
    </script>
    <script>
        function copyToClipboard(text, element) {
            const tempInput = document.createElement("input");
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);

            // Change the icon to the check icon
            const iconElement = element.querySelector('.icon');
            const originalIcon = iconElement.src;
            iconElement.src = 'data:image/svg+xml;base64,' + btoa(`
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4.04706 14C4.04706 8.55609 8.46025 4.1429 13.9042 4.1429C19.3482 4.1429 23.7613 8.55609 23.7613 14C23.7613 19.444 19.3482 23.8572 13.9042 23.8572C8.46025 23.8572 4.04706 19.444 4.04706 14Z" stroke="#000" stroke-width="2.19048" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9.52325 14L12.809 17.2858L18.2852 11.8096" stroke="#000" stroke-width="2.19048" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `);

            // Change the icon back to the original after 5 seconds
            setTimeout(() => {
                iconElement.src = originalIcon;
            }, 5000);

            // Optionally, you can show a message to the user
            console.log(`Copied the text: ${text}`);
        }
    </script>

    <script>
        var webSocketUrl = "{{ $websocket_url }}";
        const ws = new WebSocket(webSocketUrl);

        ws.onopen = function() {
            console.log('WebSocket connection established');
        };

        ws.onmessage = function(event) {
            const data = JSON.parse(event.data);
            console.log('Payment status:', data.status);
            const isPaymentUnconfirmed = data.status === 0;
            const isPaymentConfirmed = data.status === 2;
            if (isPaymentUnconfirmed) {
                // Hide all existing content
                document.querySelector('.initial-state').style.display = 'none';
                document.querySelector('.progress-message').style.display = 'block';
                document.getElementById('txid').innerText = data.txid || '';
                return;
            }
            if (isPaymentConfirmed) {
                document.querySelector('input[name="txid"]').value = data.txid || '';
                document.getElementById('server-response').submit();
            }
        };

        ws.onerror = function(error) {
            console.error('WebSocket error:', error);
        };

        ws.onclose = function() {
            console.log('WebSocket connection closed');
        };
    </script>


    <style type="text/css">
        .invoice-number {
            width: 100%;
            float: right;
            text-align: right;
            text-transform: uppercase;
            margin-bottom: 20px;
        }    
        .blockonomics-payment-wrapper {
            padding: 12px;
            display: flex;
            justify-content: center;
        }
        .initial-state {
            justify-content: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px;
        }
        .full-width-input {
            width: 100%;
            margin: 10px 0;
            padding: 10px 40px 10px 10px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            position: relative;
        }
        .input-wrapper {
            position: relative;
            width: 100%;
        }
        .icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
        .progress-message {
            display: none;
            margin: 90px 0;
            max-width: 400px;
            font-size: 16px;
            text-align: center;
        }
        #link {
            color: #007bff;
            text-decoration: underline;
        }
    </style>

    <!-- @include('portal.ninja2020.gateways.includes.pay_now') -->
@endsection

<!-- @push('footer')
    <script>
        document.getElementById('pay-now').addEventListener('click', function() {
            document.getElementById('server-response').submit();
        });
    </script>
@endpush -->
