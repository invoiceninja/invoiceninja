@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.paypal'), 'card_title' => ctrans('texts.paypal')])

@section('gateway_head')
    <meta name="client-token" content="{{ $client_token ?? '' }}"/>

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
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token', 'class' => 'hidden'])
@endsection

@section('gateway_footer')
    <script src="{{ asset('js/clients/payments/braintree-paypal.js') }}"></script>
@endsection
