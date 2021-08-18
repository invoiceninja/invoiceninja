@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="square-appId" content="{{ $gateway->company_gateway->getConfigField('applicationId') }}">
    <meta name="square-locationId" content="{{ $gateway->company_gateway->getConfigField('locationId') }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf
        <input type="text" name="sourceId" id="sourceId" hidden>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="card-container"></div>
        <div id="payment-status-container"></div>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-card'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    @if ($gateway->company_gateway->getConfigField('testMode'))
        <script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    @else
        <script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>
    @endif

    <script src="{{ asset('js/clients/payments/square-credit-card.js') }}"></script>
@endsection
