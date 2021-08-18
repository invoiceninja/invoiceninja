@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="paytrace-client-key" content="{{ $client_key }}">
    <meta name="ctrans-cvv" content="{{ ctrans('texts.cvv') }}">
    <meta name="ctrans-card_number" content="{{ ctrans('texts.card_number') }}">
    <meta name="ctrans-expires" content="{{ ctrans('texts.expires') }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="txt" id=HPF_Token name= HPF_Token hidden>
        <input type="txt" id=enc_key name= enc_key hidden>
        <input type="text" name="token" hidden>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="w-screen items-center" id="paytrace--credit-card-container">
            <div id="pt_hpf_form"></div>
        </div>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now')
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="https://protect.paytrace.com/js/protect.min.js"></script>
    <script src="{{ asset('js/clients/payments/paytrace-credit-card.js') }}"></script>
@endsection
