@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Checkout.com'])

@section('payment-head')
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="customer-email" content="{{ $customer_email }}">
    <meta name="value" content="{{ $value }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="reference" content="{{ $payment_hash }}">
@endsection

@section('payment-content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="reference" value="{{ $payment_hash }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="value" value="{{ $value }}">
        <input type="hidden" name="raw_value" value="{{ $raw_value }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        
        @isset($token)
            <input type="hidden" name="token" value="{{ $token->meta->id }}">
        @endisset
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }} (Checkout.com)
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')
    @include('portal.ninja2020.gateways.includes.store_for_future')

    @component('portal.ninja2020.components.general.card-element', ['title' => ''])
        <form class="payment-form" method="POST" action="#">
            @if(app()->environment() == 'production')
                <script async src="https://cdn.checkout.com/js/checkout.js"></script>
            @else
                <script async src="https://cdn.checkout.com/sandbox/js/checkout.js"></script>
            @endif
        </form>
    @endcomponent
@endsection

@section('payment-footer')
    <script src="{{ asset('js/clients/payments/checkout.com.js') }}"></script>
@endsection