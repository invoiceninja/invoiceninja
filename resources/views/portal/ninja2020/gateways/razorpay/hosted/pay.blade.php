@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.aio_checkout'), 'card_title' =>
ctrans('texts.aio_checkout')])

@section('gateway_head')
    <meta name="razorpay-options" content="{{ \json_encode($options) }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.aio_checkout') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    @vite('resources/js/clients/payments/razorpay-aio.js')
@endsection
