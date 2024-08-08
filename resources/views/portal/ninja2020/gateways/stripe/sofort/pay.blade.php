@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'SOFORT', 'card_title' => 'SOFORT'])

@section('gateway_head')

    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif

    <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
    <meta name="return-url" content="{{ $return_url }}">
    <meta name="amount" content="{{ $stripe_amount }}">
    <meta name="country" content="{{ $country }}">
    <meta name="customer" content="{{ $customer }}">
    <meta name="pi-client-secret" content="{{ $pi_client_secret }}">
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.sofort') }} ({{ ctrans('texts.bank_transfer') }})
    @endcomponent
    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@push('footer')
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-sofort.js')
@endpush
