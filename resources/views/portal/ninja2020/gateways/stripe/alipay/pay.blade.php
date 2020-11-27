@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Alipay', 'card_title' => 'Alipay'])

@section('gateway_head')
    <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="return-url" content="{{ $return_url }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="amount" content="{{ $stripe_amount }}">
@endsection

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.alipay') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@push('footer')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('js/clients/payments/stripe-alipay.js') }}"></script>
@endpush
