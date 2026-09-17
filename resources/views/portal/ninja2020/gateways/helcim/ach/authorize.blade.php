@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.bank_transfer'), 'card_title' => ctrans('texts.bank_transfer')])

@section('gateway_head')
    <meta name="helcim-checkout-token" content="{{ $checkout_token }}">
    <meta name="helcim-secret-token" content="{{ $secret_token }}">
@endsection

@section('gateway_content')
    @if(Session::has('error'))
        <div class="alert alert-failure mb-4">{{ Session::get('error') }}</div>
    @endif

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}"
          method="post" id="server_response">
        @csrf
        <input type="hidden" name="gateway_type_id" value="{{ App\Models\GatewayType::BANK_TRANSFER }}">
        <input type="hidden" name="transaction_data" id="transaction_data">
        <input type="hidden" name="transaction_hash" id="transaction_hash">
        <input type="hidden" name="secret_token" id="secret_token" value="{{ $secret_token }}">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.bank_transfer') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <p class="text-sm text-gray-600 mb-4">
            {{ ctrans('texts.ach_authorization', ['company' => auth()->guard('contact')->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}
        </p>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="flex justify-end">
            <button type="button"
                id="pay-button"
                onclick="openHelcimPay()"
                class="button button-primary bg-primary">
                <svg class="animate-spin h-5 w-5 text-white hidden" id="btn-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span id="btn-label">{{ ctrans('texts.add_payment_method') }}</span>
            </button>
        </div>
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="https://secure.helcim.app/helcim-pay/services/start.js"></script>
    <script>
        var checkoutToken = document.querySelector('meta[name="helcim-checkout-token"]').content;

        function openHelcimPay() {
            document.getElementById('pay-button').disabled = true;
            document.getElementById('btn-spinner').classList.remove('hidden');
            document.getElementById('btn-label').classList.add('hidden');

            window.appendHelcimPayIframe(checkoutToken);
        }

        // Listen for HelcimPay.js transaction response.
        // Current HelcimPay.js emits eventName/eventStatus, not eventType.
        window.addEventListener('message', function(event) {
            if (event.origin !== 'https://secure.helcim.app') return;

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
                document.getElementById('pay-button').disabled = false;
                document.getElementById('btn-spinner').classList.add('hidden');
                document.getElementById('btn-label').classList.remove('hidden');
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
                // (a) flat: { transactionId, status, bankAccountNumber, ... }
                // (b) nested: { data: { ... } }
                // (c) deeply nested: { data: { data: {...}, hash: "..." } }
                var responseData = (transactionResponse && transactionResponse.data) ? transactionResponse.data : transactionResponse;
                var nestedData = (responseData && responseData.data) ? responseData.data : null;

                var transactionData = (nestedData && typeof nestedData === 'object' && !Array.isArray(nestedData))
                    ? nestedData
                    : ((responseData && typeof responseData === 'object' && !Array.isArray(responseData)) ? responseData : {});

                if (!transactionData.transactionId && transactionResponse && transactionResponse.transactionId) {
                    transactionData = transactionResponse;
                }

                if (!transactionData.transactionId && eventData && eventData.transactionId) {
                    transactionData = eventData;
                }

                var transactionHash =
                    (nestedData && nestedData.hash) ||
                    (responseData && responseData.hash) ||
                    (transactionResponse && transactionResponse.hash) ||
                    (eventData && eventData.hash) ||
                    '';

                // Final fallback: if normalized payload is effectively empty,
                // send full event wrapper so backend can inspect eventStatus/eventMessage.
                if (!transactionData || Object.keys(transactionData).length === 0) {
                    transactionData = eventData || {};
                }

                document.getElementById('transaction_data').value = JSON.stringify(transactionData);
                document.getElementById('transaction_hash').value = transactionHash;

                window.removeHelcimPayIframe();
                document.getElementById('server_response').submit();
            }
        });
    </script>
@endsection
