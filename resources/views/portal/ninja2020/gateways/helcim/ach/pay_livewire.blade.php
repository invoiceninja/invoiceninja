<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="helcim-ach-payment">

    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="transaction_data" id="transaction_data">
        <input type="hidden" name="transaction_hash" id="transaction_hash">
        <input type="hidden" name="secret_token" id="secret_token" value="">
        <input type="hidden" name="use_token" id="use_token" value="0">
        <input type="hidden" name="token" id="token_id" value="">
        <input type="submit" style="display: none" id="form_btn">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.bank_transfer') }}
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
                                class="form-radio cursor-pointer toggle-payment-with-token"
                                @checked(($payment_mode ?? null) === 'saved_token' && $claimed_token_id === (string) $token->id)
                                @disabled(($payment_mode ?? null) === 'browser' || (($payment_mode ?? null) === 'saved_token' && $claimed_token_id !== (string) $token->id))/>
                            <span class="ml-1">
                                ACH **** {{ $token->meta?->last4 ?? '****' }}
                            </span>
                        </label>
                    </li>
                @endforeach
            @endif

            <li class="payment-method-item">
            <label class="payment-method-label">
                    <input
                        type="radio"
                        id="toggle-payment-with-new-bank"
                        class="form-radio cursor-pointer"
                        name="payment-type"
                        @checked(($payment_mode ?? null) !== 'saved_token')
                        @disabled(($payment_mode ?? null) === 'saved_token')/>
                    <span class="ml-1">{{ ctrans('texts.new_bank_account') }}</span>
                </label>
            </li>
        </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now'])
</div>

@assets
    <script src="https://secure.helcim.app/helcim-pay/services/start.js"></script>
    <script>
        var helcimAchCheckoutToken = '';
        var helcimAchSessionUrl = @json(route('client.payments.helcim_ach_session'));
        var helcimAchCheckoutFingerprint = @json($checkout_fingerprint);
        if (!window.helcimAchPayButtonBound) {
            window.helcimAchPayButtonBound = true;

            document.addEventListener('click', async function(e) {
            var payNowButton = e.target.closest('#pay-now');

            if (!payNowButton) {
                return;
            }

            e.preventDefault();
            var selectedPaymentType = document.querySelector('input[name="payment-type"]:checked');
            var helcimAchSelectedToken = selectedPaymentType?.dataset?.token || null;

            if (helcimAchSelectedToken) {
                document.getElementById('use_token').value = '1';
                document.getElementById('token_id').value = helcimAchSelectedToken;
                document.getElementById('server_response').submit();
            } else {
                payNowButton.disabled = true;

                try {
                    var form = document.getElementById('server_response');
                    var response = await fetch(helcimAchSessionUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({
                            payment_hash: form.querySelector('input[name="payment_hash"]').value,
                            company_gateway_id: form.querySelector('input[name="company_gateway_id"]').value,
                            checkout_fingerprint: helcimAchCheckoutFingerprint,
                        }),
                    });
                    var session = await response.json();

                    if (!response.ok || !session.checkout_token || !session.secret_token) {
                        throw new Error(session.message || 'Unable to initialize Helcim ACH checkout.');
                    }

                    helcimAchCheckoutToken = session.checkout_token;
                    document.getElementById('secret_token').value = session.secret_token;
                    document.querySelectorAll('.toggle-payment-with-token').forEach(function(input) {
                        input.disabled = true;
                    });
                    window.appendHelcimPayIframe(helcimAchCheckoutToken);
                } catch (error) {
                    console.error(error);
                    window.alert(error.message || 'Unable to initialize Helcim ACH checkout.');
                    payNowButton.disabled = false;
                }
            }
            });
        }

        window.addEventListener('message', function(event) {
            if (event.origin !== 'https://secure.helcim.app') return;

            var eventData;
            try {
                eventData = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
            } catch (e) {
                return;
            }

            if (!eventData || eventData.eventName !== 'helcim-pay-js-' + helcimAchCheckoutToken) {
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

                document.getElementById('transaction_data').value = JSON.stringify(transactionData);
                document.getElementById('transaction_hash').value = transactionHash;
                document.getElementById('use_token').value = '0';
                window.removeHelcimPayIframe();
                document.getElementById('server_response').submit();
            }
        });
    </script>
@endassets
