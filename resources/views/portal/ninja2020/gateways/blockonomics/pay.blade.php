@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_Crypto'), 'card_title' => ctrans('texts.payment_type_Crypto')])

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <!-- @include('portal.ninja2020.gateways.includes.payment_details') -->
    <div class="blockonomics-payment-wrapper">
        <div class="invoice-number">Invoice #{{$invoice_number}}</div>
        <div>To pay, send exactly this BTC amount</div>
        <div class="full-width-input" onclick='copyToClipboard("{{$btc_amount}}")'>
            {{$btc_amount}} BTC <span style="color: gray;">≈ {{$amount}} {{$currency}}</span>
        </div>
        <div>To this bitcoin address</div>
        <input class="full-width-input" id="btcAddress" value="{{$btc_address}}" readonly onclick='copyToClipboard("{{$btc_address}}")'>
        <div id="countdown"></div>
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

            document.getElementById("countdown").innerHTML = 
                "0" + minutes + ":" +
                (seconds < 10 ? "0" : "") + seconds + " min";
        }

        // Update immediately and then every second
        updateCountdown();
        var x = setInterval(updateCountdown, 1000);
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
            const isPaymentConfirmed = data.status == 2;
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

    <script>
        function copyToClipboard(text) {
            const tempInput = document.createElement("input");
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            // TODO: Show a success message instead of an alert
            alert(`Copied the text: ${text}`);
        }
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
            justify-content: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px;
        }
        .full-width-input {
            width: 100%;
            margin: 10px 0;
            padding: 10px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
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
