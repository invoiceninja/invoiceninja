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
                        checked/>
                    <span class="ml-1 cursor-pointer">{{ ctrans('texts.new_bank_account') }}</span>
                </label>
            </li>
        </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now'])
</div>

@assets
    <script src="https://helcimpaystatic.com/sdk/javascript/1.0.0/helcim-pay.js"></script>
    <script>
        var helcimAchCheckoutToken = '{{ $checkout_token }}';
        var helcimAchSecretToken = '{{ $secret_token }}';
        var helcimAchSelectedToken = null;

        document.querySelectorAll('.toggle-payment-with-token').forEach(function(radio) {
            radio.addEventListener('change', function() {
                helcimAchSelectedToken = this.dataset.token;
            });
        });

        document.getElementById('toggle-payment-with-new-bank')?.addEventListener('change', function() {
            helcimAchSelectedToken = null;
        });

        document.getElementById('pay-now').addEventListener('click', function(e) {
            e.preventDefault();

            if (helcimAchSelectedToken) {
                document.getElementById('use_token').value = '1';
                document.getElementById('token_id').value = helcimAchSelectedToken;
                document.getElementById('server_response').submit();
            } else {
                this.disabled = true;
                window.appendHelcimIframe(helcimAchCheckoutToken);
            }
        });

        window.addEventListener('message', function(event) {
            if (event.origin.indexOf('helcim') === -1) return;

            var eventData = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;

            if (eventData.eventType === 'HELCIM_PAY_JS_CLOSE' || eventData.eventType === 'HELCIM_PAY_JS_INIT_ERROR') {
                window.removeHelcimIframe();
                document.getElementById('pay-now').disabled = false;
                return;
            }

            if (eventData.eventType === 'HELCIM_PAY_JS_TRANSACTION_RESPONSE') {
                var transactionResponse = eventData.eventMessage;
                document.getElementById('transaction_data').value = JSON.stringify(transactionResponse.data);
                document.getElementById('transaction_hash').value = transactionResponse.hash;
                document.getElementById('use_token').value = '0';
                window.removeHelcimIframe();
                document.getElementById('server_response').submit();
            }
        });
    </script>
@endassets
