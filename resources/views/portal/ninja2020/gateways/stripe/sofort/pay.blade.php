@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'SOFORT', 'card_title' => 'SOFORT'])

@section('gateway_head')
    <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="return-url" content="{{ $return_url }}">
    <meta name="amount" content="{{ $stripe_amount }}">
    <meta name="country" content="{{ $country }}">
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
    <script src="{{ asset('js/clients/payments/stripe-sofort.js') }}"></script>
@endpush
