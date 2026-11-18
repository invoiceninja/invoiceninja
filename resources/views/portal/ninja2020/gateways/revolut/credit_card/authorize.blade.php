@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="instant-payment" content="yes" />
    <meta name="revolut-only-authorization" content="true" />
    <meta name="revolut-order-token" content="{{ $order_token }}">
    <meta name="revolut-mode" content="{{ $revolut_mode }}">
    <meta name="revolut-cardholder-name" content="{{ $cardholder_name }}">
    <meta name="revolut-customer-email" content="{{ $customer_email }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="revolut-card-field" class="rounded border border-gray-300 px-3 py-3"></div>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="{{ $embed_url }}"></script>
    @vite('resources/js/clients/payments/revolut-credit-card.js')
@endsection
