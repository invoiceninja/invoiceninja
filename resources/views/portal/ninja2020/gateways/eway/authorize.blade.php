@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' =>
ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="public-api-key" content="{{ $public_api_key }}">
    <meta name="translation-card-name" content="{{ ctrans('texts.cardholder_name') }}">
    <meta name="translation-expiry_date" content="{{ ctrans('texts.date') }}">
    <meta name="translation-card_number" content="{{ ctrans('texts.card_number') }}">
    <meta name="translation-cvv" content="{{ ctrans('texts.cvv') }}">
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server-response">
        @csrf

        <input type="hidden" id="securefieldcode" name="securefieldcode">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
    </form>

    @if (!Request::isSecure())
        <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
    @endif

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="eway-secure-panel"></div>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-card', 'disabled' => true])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="https://secure.ewaypayments.com/scripts/eWAY.min.js" data-init="false"></script>
    @vite('resources/js/clients/payments/eway-credit-card.js')
@endsection
