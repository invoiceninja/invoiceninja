@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="helcim-token" content="{{ $gateway->company_gateway->getConfigField('apiToken') }}">
    <meta name="helcim-test-mode" content="{{ $gateway->company_gateway->getConfigField('testMode') ? 'true' : 'false' }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.card_details')])
        <div id="helcim-card-container"></div>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.default_payment_method')])
        <input type="checkbox" id="proxy_is_default" class="form-checkbox mr-1">
        <label for="proxy_is_default" class="cursor-pointer">{{ ctrans('texts.set_as_default') }}</label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-card'])
@endsection

@section('gateway_footer')
    <script src="https://myhelcim.com/js/version2.js"></script>
    @vite('resources/js/clients/payments/helcim-credit-card.js')
@endsection