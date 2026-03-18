@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="customer-email" content="{{ $customer_email }}">
    <meta name="value" content="{{ $value }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="reference" content="{{ $payment_hash }}">
    <meta name="instant-payment" content="yes" />
    <meta name="cardholder_name" content="{{ $cardholder_name }}">
    @if($use_flow ?? false)
    <meta name="payment-session-id" content="{{ $payment_session_id ?? '' }}">
    <meta name="payment-session-token" content="{{ $payment_session_token ?? '' }}">
    <meta name="environment" content="{{ $environment ?? 'sandbox' }}">
    @endif

    @include('portal.ninja2020.gateways.checkout.credit_card.includes.styles')

    @if(!($use_flow ?? false))
    <script src="https://cdn.checkout.com/js/framesv2.min.js"></script>
    @endif
@endsection

@section('gateway_content')
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
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->hashed_id }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"
                        {{ $loop->first ? 'checked' : '' }}/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endif

        <label>
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                {{ count($tokens) == 0 ? 'checked' : '' }}/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
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
@endsection

@section('gateway_footer')
    @if($use_flow ?? false)
    @vite('resources/js/clients/payments/checkout-credit-card-flow.js')
    @else
    @vite('resources/js/clients/payments/checkout-credit-card.js')
    @endif
@endsection
