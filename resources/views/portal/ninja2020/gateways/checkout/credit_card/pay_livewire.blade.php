<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="checkout-credit-card-payment">
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="customer-email" content="{{ $customer_email }}">
    <meta name="value" content="{{ $value }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="reference" content="{{ $payment_hash }}">
    <meta name="cardholder_name" content="{{ $cardholder_name }}">
    @if($use_flow ?? false)
    <meta name="payment-session-id" content="{{ $payment_session_id ?? '' }}">
    <meta name="payment-session-token" content="{{ $payment_session_token ?? '' }}">
    <meta name="environment" content="{{ $environment ?? 'sandbox' }}">
    @endif

    @include('portal.ninja2020.gateways.checkout.credit_card.includes.styles')

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="reference" value="{{ $payment_hash }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="value" value="{{ $value }}">
        <input type="hidden" name="raw_value" value="{{ $raw_value }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="pay_with_token" value="false">
        <input type="hidden" name="token" value="">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
    {{ ctrans('texts.credit_card') }} (Checkout.com)
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])


        <ul class="list-none space-y-2">
            @if(count($tokens) > 0)
                @foreach($tokens as $token)
                <li class="py-2 hover:bg-gray-100 rounded transition-colors duration-150">
                    <label class="flex items-center cursor-pointer px-2">
                        <input
                            type="radio"
                            data-token="{{ $token->hashed_id }}"
                            name="payment-type"
                            class="form-radio text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token"
                            {{ $loop->first ? 'checked' : '' }}/>
                        <span class="ml-2 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                    </label>
                </li>
                @endforeach
            @endif

            <li class="py-2 hover:bg-gray-100 rounded transition-colors duration-150">
                <label class="flex items-center cursor-pointer px-2">
                    <input
                        type="radio"
                        id="toggle-payment-with-credit-card"
                        class="form-radio text-indigo-600 rounded-full cursor-pointer"
                        name="payment-type"
                        {{ count($tokens) == 0 ? 'checked' : '' }}/>
                    <span class="ml-2 cursor-pointer">{{ __('texts.new_card') }}</span>
                </label>
            </li>
        </ul>


    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
    @if($use_flow ?? false)
    <div id="flow-container"></div>
    <p id="flow-error-message" class="text-red-600 mt-2 hidden" role="alert"></p>
    @else
    <div id="checkout--container">
        <form class="xl:flex xl:justify-center" id="payment-form" method="POST" action="#">
            <div class="one-liner">
                <div class="card-frame">
                    <!-- form will be added here -->
                </div>
                <!-- add submit button -->
                <button id="pay-button" disabled>
                    {{ ctrans('texts.pay') }} {{ App\Utils\Number::formatMoney($total['amount_with_fee'], $client) }}
                </button>
            </div>
            <p class="success-payment-message"></p>
        </form>
    </div>
    @endif
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
    <div class="hidden" id="pay-now-with-token--container">
        @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token'])
    </div>
    @endcomponent

    @assets
    <script src="https://checkout-web-components.checkout.com/index.js" async></script>
    @if(!($use_flow ?? false))
    <script src="https://cdn.checkout.com/js/framesv2.min.js"></script>
    @vite('resources/js/clients/payments/checkout-credit-card.js')
    @endif
    @endassets

    @if($use_flow ?? false)
    @script
    <script>
        (function() {
            function getMeta(name) {
                var el = document.querySelector('meta[name="' + name + '"]');
                return el ? el.getAttribute('content') || '' : '';
            }

            function initFlow() {
                var sessionId = getMeta('payment-session-id');
                var sessionToken = getMeta('payment-session-token');
                var publicKey = getMeta('public-key');
                var environment = getMeta('environment') || 'sandbox';

                if (!sessionId || !sessionToken || !publicKey) {
                    var errEl = document.getElementById('flow-error-message');
                    if (errEl) {
                        errEl.textContent = 'Payment session is missing. Please refresh the page.';
                        errEl.classList.remove('hidden');
                    }
                    return;
                }

                var paymentSession = { id: sessionId, payment_session_token: sessionToken };

                function boot() {
                    if (typeof window.CheckoutWebComponents === 'undefined') {
                        setTimeout(boot, 100);
                        return;
                    }

                    window.CheckoutWebComponents({
                        paymentSession: paymentSession,
                        publicKey: publicKey,
                        environment: environment,
                        onPaymentCompleted: function(_self, paymentResponse) {
                            var form = document.getElementById('server-response');
                            if (!form) return;
                            var gatewayInput = form.querySelector('input[name="gateway_response"]');
                            var storeCardInput = form.querySelector('input[name="store_card"]');
                            if (gatewayInput) {
                                gatewayInput.value = JSON.stringify({ id: paymentResponse.id });
                            }
                            if (storeCardInput) {
                                var saveCheckbox = document.querySelector('input[name=token-billing-checkbox]:checked');
                                storeCardInput.value = saveCheckbox ? saveCheckbox.value : 'false';
                            }
                            form.submit();
                        },
                        onError: function(event) {
                            var errEl = document.getElementById('flow-error-message');
                            if (errEl) {
                                errEl.textContent = (event.detail && event.detail.message) || 'Payment failed. Please try again.';
                                errEl.classList.remove('hidden');
                            }
                        },
                    }).then(function(checkout) {
                        var flowComponent = checkout.create('flow');
                        flowComponent.mount('#flow-container');
                    }).catch(function(err) {
                        var errEl = document.getElementById('flow-error-message');
                        if (errEl) {
                            errEl.textContent = (err && err.message) || 'Unable to load payment form. Please refresh the page.';
                            errEl.classList.remove('hidden');
                        }
                    });
                }

                boot();
            }

            // Token toggle handlers
            var flowContainer = document.getElementById('flow-container');
            var tokenContainer = document.getElementById('pay-now-with-token--container');
            var saveCardContainer = document.getElementById('save-card--container');

            Array.from(document.getElementsByClassName('toggle-payment-with-token') || []).forEach(function(el) {
                el.addEventListener('click', function() {
                    if (flowContainer) flowContainer.classList.add('hidden');
                    if (tokenContainer) tokenContainer.classList.remove('hidden');
                    if (saveCardContainer) saveCardContainer.style.display = 'none';
                    var tokenInput = document.querySelector('input[name=token]');
                    if (tokenInput) tokenInput.value = el.dataset.token || '';
                });
            });

            var newCardRadio = document.getElementById('toggle-payment-with-credit-card');
            if (newCardRadio) {
                newCardRadio.addEventListener('click', function() {
                    if (flowContainer) flowContainer.classList.remove('hidden');
                    if (tokenContainer) tokenContainer.classList.add('hidden');
                    if (saveCardContainer) saveCardContainer.style.display = 'grid';
                    var tokenInput = document.querySelector('input[name=token]');
                    if (tokenInput) tokenInput.value = '';
                });
            }

            var payNowBtn = document.getElementById('pay-now-with-token');
            if (payNowBtn) {
                payNowBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    payNowBtn.disabled = true;
                    var svg = payNowBtn.querySelector('svg');
                    var span = payNowBtn.querySelector('span');
                    if (svg) svg.classList.remove('hidden');
                    if (span) span.classList.add('hidden');
                    document.getElementById('server-response').submit();
                });
            }

            if (flowContainer) {
                initFlow();
            }

            // Pre-select first token to set initial UI state
            var firstToken = document.querySelector('input.toggle-payment-with-token');
            if (firstToken) {
                firstToken.click();
            }
        })();
    </script>
    @endscript
    @endif
</div>