@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Przelewy24', 'card_title' => 'Przelewy24'])

@section('gateway_head')
    <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
    <meta name="amount" content="{{ $stripe_amount }}">
    <meta name="country" content="{{ $country }}">
    <meta name="return-url" content="{{ $return_url }}">
    <meta name="customer" content="{{ $customer }}">
    <meta name="pi-client-secret" content="{{ $pi_client_secret }}">
@endsection

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.przelewy24') }} ({{ ctrans('texts.bank_transfer') }})
    @endcomponent

    @include('portal.ninja2020.gateways.stripe.przelewy24.przelewy24')
    @include('portal.ninja2020.gateways.includes.save_card')
    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@push('footer')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('js/clients/payments/stripe-przelewy24.js') }}"></script>
@endpush
