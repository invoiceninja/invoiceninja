<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="braintree-credit-card-payment" data-retry-message="{{ ctrans('texts.try_again') }}" data-error-message="{{ ctrans('texts.generic_gateway_error') }}" data-require-cvv="{{ $gateway->company_gateway->require_cvv ? 'true' : 'false' }}" data-verification-error="{{ ctrans('texts.unable_to_verify_payment_method') }}">
    <meta name="client-token" content="{{ $client_token ?? '' }}" />

    <div id="errors" class="alert alert-failure mb-4" role="alert" hidden></div>

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="threeds_enable" value="{!! $threeds_enable !!}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
        <input type="hidden" name="client-data">
        <input type="hidden" name="threeds" value="{{ json_encode($threeds) }}">
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
                            data-token="{{ $token->token }}"
                            data-nonce="{{ $saved_cards[$token->token]['nonce'] ?? '' }}"
                            data-bin="{{ $saved_cards[$token->token]['bin'] ?? '' }}"
                            name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token"/>
                        <span class="ml-1">**** {{ $token->meta?->last4 }}</span>
                    </label>
                </li>
                @endforeach
            @endif

            <li class="payment-method-item">
                <label class="payment-method-label">
                    <input
                        type="radio"
                        id="toggle-payment-with-credit-card"
                        class="form-radio cursor-pointer"
                        name="payment-type"
                        checked/>
                    <span class="ml-1">{{ __('texts.new_card') }}</span>
                </label>
            </li>    
        </ul>

    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        @include('portal.ninja2020.gateways.braintree.credit_card.fields')
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token', 'class' => 'hidden'])

</div>

@assets
    <script src="https://js.braintreegateway.com/web/3.147.0/js/client.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.147.0/js/data-collector.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.147.0/js/hosted-fields.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.147.0/js/three-d-secure.min.js"></script>

    @vite('resources/js/clients/payments/braintree-credit-card.js')
@endassets
