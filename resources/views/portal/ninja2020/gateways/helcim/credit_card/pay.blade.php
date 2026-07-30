@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="helcim-checkout-token" content="{{ $checkout_token }}">
    <meta name="helcim-secret-token" content="{{ $secret_token }}">
@endsection

@section('gateway_content')
    @if(Session::has('error'))
        <div class="alert alert-failure mb-4">{{ Session::get('error') }}</div>
    @endif

    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="transaction_data" id="transaction_data">
        <input type="hidden" name="transaction_hash" id="transaction_hash">
        <input type="hidden" name="secret_token" id="secret_token" value="{{ $secret_token }}">
        <input type="hidden" name="use_token" id="use_token" value="0">
        <input type="hidden" name="token" id="token_id" value="">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="payment-method-list">
            @if(count($tokens) > 0)
                @foreach($tokens as $token)
                    <li class="payment-method-item">
                    <label class="payment-method-label">
                    <input
                                type="radio"
                                data-token="{{ $token->hashed_id }}"
                                name="payment-type"
                                class="form-radio cursor-pointer toggle-payment-with-token"/>
                            <span class="ml-1">
                                {{ $token->meta?->brand ?? ctrans('texts.credit_card') }}
                                **** {{ $token->meta?->last4 }}
                            </span>
                        </label>
                    </li>
                @endforeach
            @endif

            <li class="payment-method-item">
            <label class="payment-method-label">
                    <input
                        type="radio"
                        id="toggle-payment-with-new-card"
                        class="form-radio cursor-pointer"
                        name="payment-type"
                        checked/>
                    <span class="ml-1">{{ ctrans('texts.new_card') }}</span>
                </label>
            </li>
        </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now'])
@endsection

@section('gateway_footer')
    <script src="https://secure.helcim.app/helcim-pay/services/start.js"></script>
    <script>
        var checkoutToken = document.querySelector('meta[name="helcim-checkout-token"]').content;
        document.getElementById('pay-now').addEventListener('click', function(e) {
            e.preventDefault();
            var selectedPaymentType = document.querySelector('input[name="payment-type"]:checked');
            var selectedToken = selectedPaymentType?.dataset?.token || null;

            if (selectedToken) {
                // Pay with saved token
                document.getElementById('use_token').value = '1';
                document.getElementById('token_id').value = selectedToken;
                document.getElementById('server_response').submit();
            } else {
                // Open HelcimPay.js modal for new card
                if (!checkoutToken) {
                    console.error('Helcim checkout token is missing.');
                    this.disabled = false;
                    return;
                }

                this.disabled = true;
                window.appendHelcimPayIframe(checkoutToken);
            }
        });

        // Listen for HelcimPay.js transaction response.
        // Current HelcimPay.js emits eventName/eventStatus, not eventType.
        window.addEventListener('message', function(event) {
            if (event.origin.indexOf('helcim') === -1) return;

            var eventData;
            try {
                eventData = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
            } catch (e) {
                return;
            }

            if (!eventData || eventData.eventName !== 'helcim-pay-js-' + checkoutToken) {
                return;
            }

            if (eventData.eventStatus === 'HIDE' || eventData.eventStatus === 'ABORTED') {
                window.removeHelcimPayIframe();
                document.getElementById('pay-now').disabled = false;
                return;
            }

            if (eventData.eventStatus === 'SUCCESS') {
                var transactionResponse = eventData.eventMessage || {};

                if (typeof transactionResponse === 'string') {
                    try {
                        transactionResponse = JSON.parse(transactionResponse);
                    } catch (e) {
                        transactionResponse = {};
                    }
                }

                // Normalize HelcimPay.js response — eventMessage can be:
                // (a) flat: { transactionId, status, cardToken, ... }
                // (b) nested: { data: { data: { transactionId, ... }, hash: "..." } }
                var transactionData, transactionHash;
                if (transactionResponse && transactionResponse.transactionId) {
                    transactionData = transactionResponse;
                    transactionHash = transactionResponse.hash || '';
                } else {
                    var responseData = (transactionResponse && transactionResponse.data) ? transactionResponse.data : {};
                    transactionData = (responseData && responseData.data) ? responseData.data : responseData;
                    transactionHash = (responseData && responseData.hash) ? responseData.hash : ((transactionResponse && transactionResponse.hash) ? transactionResponse.hash : '');
                }

                document.getElementById('transaction_data').value = JSON.stringify(transactionData);
                document.getElementById('transaction_hash').value = transactionHash;
                document.getElementById('use_token').value = '0';

                window.removeHelcimPayIframe();
                document.getElementById('server_response').submit();
            }
        });
    </script>
@endsection