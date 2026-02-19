@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.pay_now'), 'card_title' => ctrans('texts.pay_now')])

@section('gateway_head')
    <meta name="revolut-checkout-url" content="{{ $checkout_url }}">
    <meta name="revolut-order-id" content="{{ $order_id }}">
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
    Revolut
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    @vite('resources/js/clients/payments/revolut-pay.js')
@endsection