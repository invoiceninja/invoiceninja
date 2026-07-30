<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="helcim-credit-card-payment">

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
        <input type="submit" style="display: none" id="form_btn">
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
</div>

@assets
    <script src="https://secure.helcim.app/helcim-pay/services/start.js"></script>
    <script>
        var helcimCheckoutToken = '{{ $checkout_token }}';
        var helcimSecretToken = '{{ $secret_token }}';
        if (!window.helcimCreditCardPayButtonBound) {
            window.helcimCreditCardPayButtonBound = true;

            document.addEventListener('click', function(e) {
            var payNowButton = e.target.closest('#pay-now');

            if (!payNowButton) {
                return;
            }

            e.preventDefault();
            var selectedPaymentType = document.querySelector('input[name="payment-type"]:checked');
            var helcimSelectedToken = selectedPaymentType?.dataset?.token || null;

            if (helcimSelectedToken) {
                document.getElementById('use_token').value = '1';
                document.getElementById('token_id').value = helcimSelectedToken;
                document.getElementById('server_response').submit();
            } else {
                if (!helcimCheckoutToken) {
                    console.error('Helcim checkout token is missing.');
                    payNowButton.disabled = false;
                    return;
                }

                payNowButton.disabled = true;
                window.appendHelcimPayIframe(helcimCheckoutToken);
            }
            });
        }

        window.addEventListener('message', function(event) {
            if (event.origin.indexOf('helcim') === -1) return;

            var eventData;
            try {
                eventData = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
            } catch (e) {
                return;
            }

            if (!eventData || eventData.eventName !== 'helcim-pay-js-' + helcimCheckoutToken) {
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
@endassets