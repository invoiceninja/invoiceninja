@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.paypal'), 'card_title' => ctrans('texts.paypal')])

@section('gateway_head')
    <meta name="client-token" content="{{ $client_token ?? '' }}"/>
    <meta name="instant-payment" content="yes" />

    <script src="https://js.braintreegateway.com/web/3.76.2/js/client.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.76.2/js/paypal-checkout.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.76.2/js/data-collector.min.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
        <input type="hidden" name="client-data">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.paypal') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4 block mt-2">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">{{ property_exists($token->meta, 'email') ? $token->meta?->email : 'no email provided'}}</span>
                </label>
            @endforeach
        @endisset

        <label class="block mt-2">
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_account') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="paypal-button"></div>

        <svg id="paypal-spinner" class="animate-spin h-8 w-8 text-primary hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>            
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token', 'class' => 'hidden'])
@endsection

@section('gateway_footer')
    @vite('resources/js/clients/payments/braintree-paypal.js')
@endsection
