@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.bank_transfer')])

@section('gateway_head')
    <meta name="wepay-environment" content="{{ config('ninja.wepay.environment') }}">
    <meta name="wepay-client-id" content="{{ config('ninja.wepay.client_id') }}">
    <meta name="contact-email" content="{{ $contact->email }}">
    <meta name="country_code" content="{{$country_code}}">

    <script type="text/javascript" src="https://static.wepay.com/min/js/tokenization.4.latest.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="2">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="bank_account_id" id="bank_account_id">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.bank_account') }}
    @endcomponent

@endsection

@section('gateway_footer')
    @vite('resources/js/clients/payment_methods/wepay-bank-account.js')
@endsection
