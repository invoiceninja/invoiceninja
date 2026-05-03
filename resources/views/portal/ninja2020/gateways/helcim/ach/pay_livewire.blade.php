<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="helcim-ach-payment">

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
        {{ ctrans('texts.bank_transfer') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="list-none">
            @if(count($tokens) > 0)
                @foreach($tokens as $token)
                    <li class="py-2 cursor-pointer">
                        <label class="flex items-center cursor-pointer px-2">
                            <input
                                type="radio"
                                data-token="{{ $token->hashed_id }}"
                                name="payment-type"
                                @if($loop->first) checked @endif
                                class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token"/>
                            <span class="ml-1 cursor-pointer">
                                ACH **** {{ $token->meta?->last4 ?? '****' }}
                            </span>
                        </label>
                    </li>
                @endforeach
            @endif

            <li class="py-2 cursor-pointer">
                <label class="flex items-center cursor-pointer px-2">
                    <input
                        type="radio"
                        id="toggle-payment-with-new-bank"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer"
                        name="payment-type"
                        @if(count($tokens) === 0) checked @endif/>
                    <span class="ml-1 cursor-pointer">{{ ctrans('texts.new_bank_account') }}</span>
                </label>
            </li>
        </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now'])
</div>

@assets
    <script src="https://secure.helcim.app/helcim-pay/services/start.js"></script>
    <script>
        var helcimAchCheckoutToken = '{{ $checkout_token }}';
        var helcimAchSecretToken = '{{ $secret_token }}';
        if (!window.helcimAchPayButtonBound) {
            window.helcimAchPayButtonBound = true;

            document.addEventListener('click', function(e) {
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
                if (!helcimAchCheckoutToken) {
                    console.error('Helcim ACH checkout token is missing.');
                    payNowButton.disabled = false;
                    return;
                }

                payNowButton.disabled = true;
                window.appendHelcimPayIframe(helcimAchCheckoutToken);
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

                var responseData = transactionResponse.data || {};
                var transactionData = responseData.data || responseData;
                var transactionHash = responseData.hash || transactionResponse.hash;

                document.getElementById('transaction_data').value = JSON.stringify(transactionData);
                document.getElementById('transaction_hash').value = transactionHash;
                document.getElementById('use_token').value = '0';
                window.removeHelcimPayIframe();
                document.getElementById('server_response').submit();
            }
        });
    </script>
@endassets
