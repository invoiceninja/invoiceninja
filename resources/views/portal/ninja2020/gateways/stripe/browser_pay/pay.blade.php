@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.browser_pay'), 'card_title' => ctrans('texts.browser_pay')])

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif

    <meta name="stripe-pi-client-secret" content="{{ $pi_client_secret }}">
    <meta name="no-available-methods" content="{{ json_encode(ctrans('texts.no_available_methods')) }}">
    <meta name="payment-request-data" content="{{ json_encode($payment_request_data) }}">

    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="store_card">
        <input type="hidden" name="token">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="payment-request-button"></div>
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-browserpay.js')
@endsection