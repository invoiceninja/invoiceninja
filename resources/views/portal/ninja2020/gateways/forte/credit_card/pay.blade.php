@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="forte-api-login-id" content="{{$gateway->company_gateway->getConfigField("apiLoginId")}}">
    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/forte-card-js.min.js') }}"></script>

    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="card_brand" id="card_brand">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{$payment_method_id}}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
        <input type="hidden" name="token" id="token"/>
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="submit" style="display: none" id="form_btn">
    </form>

    <div id="forte_errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
     @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => 'Pay with Credit Card'])
       @include('portal.ninja2020.gateways.forte.includes.credit_card')
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')

@endsection

@section('gateway_footer')
    @if($gateway->company_gateway->getConfigField('testMode'))
        <script type="text/javascript" src="https://sandbox.forte.net/api/js/v1"></script>
    @else
        <script type="text/javascript" src="https://api.forte.net/js/v1"></script>
    @endif
    
    @vite('resources/js/clients/payments/forte-credit-card-payment.js')
@endsection

@push('footer')
<script defer>
 
$(function() {

    document.getElementsByClassName("expiry")[0].addEventListener('change', function() {

    str = document.getElementsByClassName("expiry")[0].value.replace(/\s/g, '');
    const expiryArray = str.split("/");

    document.getElementsByName('expiry-month')[0].value = expiryArray[0];
    document.getElementsByName('expiry-year')[0].value = expiryArray[1];

    });

});

</script>
@endpush