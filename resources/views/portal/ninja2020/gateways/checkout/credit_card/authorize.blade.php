@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
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
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf

        <input type="hidden" name="payment_method_id" value="{{ \App\Models\GatewayType::CREDIT_CARD }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        @if($use_flow ?? false)
        <div id="flow-container"></div>
        <p id="flow-error-message" class="text-red-600 mt-2 hidden" role="alert"></p>
        @else
        <div id="checkout--container">
            <form class="xl:flex xl:justify-center" id="authorization-form" method="POST" action="#">
                <div class="one-liner">
                    <div class="card-frame">
                        <!-- form will be added here -->
                    </div>
                    <!-- add submit button -->
                    <button id="pay-button" disabled>
                        {{ ctrans('texts.add_payment_method') }}
                    </button>
                </div>
                <p class="success-payment-message"></p>
            </form>
        </div>
        @endif
    @endcomponent
@endsection

@section('gateway_footer')
    @if($use_flow ?? false)
    @vite('resources/js/clients/payment_methods/authorize-checkout-card-flow.js')
    @else
    @vite('resources/js/clients/payment_methods/authorize-checkout-card.js')
    @endif
@endsection
