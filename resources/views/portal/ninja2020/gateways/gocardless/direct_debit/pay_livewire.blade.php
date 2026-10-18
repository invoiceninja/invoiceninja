<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="gocardless-direct-debit-payment">
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="source" value="">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="payment-method-list">
            @foreach($tokens as $token)
                <li class="payment-method-item">
                    <label class="payment-method-label">
                        <input
                            type="radio"
                            data-token="{{ $token->token }}"
                            name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token"/>
                        <span class="ml-1">{{ App\Models\GatewayType::getAlias($token->gateway_type_id) }} {{ $token->getGatewayAccountName() }}</span>
                    </label>
                </li>
            @endforeach

            <li class="payment-method-item">
                <label class="payment-method-label">
                    <input
                        type="radio"
                        id="toggle-payment-with-new-gocardless-account"
                        class="form-radio cursor-pointer"
                        name="payment-type"
                        @checked(count($tokens) === 0) />
                    <span class="ml-1">{{ ctrans('texts.new_bank_account') }}</span>
                </label>
            </li>
        </ul>
    @endcomponent

    <div id="gocardless-payment-action">
        @include('portal.ninja2020.gateways.includes.pay_now')
    </div>
</div>

@script
    <script>
        // Initial component load
        Livewire.hook('component.init', ({ component, cleanup }) => {
            initializePaymentHandlers();
        })

        function initializePaymentHandlers() {
            const root = document.getElementById('gocardless-direct-debit-payment');

            if (!root) {
                return;
            }

            const source = root.querySelector('input[name=source]');

            // Handle payment token selection
            Array
                .from(root.getElementsByClassName('toggle-payment-with-token'))
                .forEach((element) => element.onclick = (event) => {
                    source.value = event.target.dataset.token;
                });

            root.querySelector('#toggle-payment-with-new-gocardless-account').onclick = () => {
                if (source) {
                    source.value = '';
                }

            };

            // Handle pay now button
            const payNowButton = root.querySelector('#pay-now');
            if (payNowButton) {
                payNowButton.onclick = (event) => {
                    const button = event.currentTarget;

                    // Disable button and update UI
                    button.disabled = true;
                    button.querySelector('svg').classList.remove('hidden');
                    button.querySelector('span').classList.add('hidden');

                    // Submit form
                    root.querySelector('#server-response').submit();
                };
            }

            // Auto-select first payment method
            const first = root.querySelector('input[name="payment-type"]');
            if (first) {
                first.click();
            }
        }
    </script>
@endscript
