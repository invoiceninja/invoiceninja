@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="client-token" content="{{ $client_token ?? '' }}"/>

    <script src='https://js.braintreegateway.com/web/dropin/1.33.4/js/dropin.min.js'></script>
    {{-- <script src="https://js.braintreegateway.com/web/3.76.2/js/client.min.js"></script> --}}
    <script src="https://js.braintreegateway.com/web/3.87.0/js/data-collector.min.js"></script>

<!-- Load the client component. -->
<script src='https://js.braintreegateway.com/web/3.87.0/js/client.min.js'></script>

    <style>
        [data-braintree-id="toggle"] {
            display: none;
        }
    </style>
@endsection

@section('gateway_content')
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
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label>
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="dropin-container"></div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token', 'class' => 'hidden'])
@endsection

@section('gateway_footer')

    <script defer src="{{ asset('js/clients/payments/braintree-credit-card.js') }}"></script>

@endsection

<div id="threeds"></div>