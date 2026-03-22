@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="square-appId" content="{{ $gateway->company_gateway->getConfigField('applicationId') }}">
    <meta name="square-locationId" content="{{ $gateway->company_gateway->getConfigField('locationId') }}">
    <meta name="square_contact" content="{{ json_encode($square_contact) }}">
    <meta name="currencyCode" content="{{ $currencyCode }}">
    <meta name="only-authorization" content="true" />
    <meta name="instant-payment" content="yes" />

    <style>
        .loader {
            border-top-color: #3498db;
            -webkit-animation: spinner 1.5s linear infinite;
            animation: spinner 1.5s linear infinite;
        }

        @-webkit-keyframes spinner {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }

        @keyframes spinner {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="is_default" value="0">
        <input type="hidden" name="sourceId" id="sourceId">
        <input type="hidden" name="verificationToken" id="verificationToken">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="flex flex-col" id="loader">
            <div class="loader ease-linear rounded-full border-4 border-t-4 border-gray-200 h-12 w-12 mb-4"></div>
        </div>
        <div id="card-container"></div>
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

    @vite('resources/js/clients/payments/square-credit-card.js')
@endsection
