@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="wepay-environment" content="{{ config('ninja.wepay.environment') }}">
    <meta name="wepay-action" content="authorize">
    <meta name="wepay-client-id" content="{{ config('ninja.wepay.client_id') }}">

    <meta name="contact-email" content="{{ $contact->email }}">
    <meta name="client-postal-code" content="{{ $contact->client->postal_code }}">
    <meta name="country_code" content="{{$country_code}}">

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>

    <script src="{{ asset('build/public/js/card-js.min.js/card-js.min.js') }}"></script>
    <link href="{{ asset('build/public/css/card-js.min.css/card-js.min.css') }}" rel="stylesheet" type="text/css">

    <script type="text/javascript" src="https://static.wepay.com/min/js/tokenization.4.latest.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="credit_card_id" id="credit_card_id">
    </form>

    @if(!Request::isSecure())
        <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
    @endif

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.wepay.includes.credit_card')

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'card_button'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    @vite('resources/js/clients/payments/wepay-credit-card.js')
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