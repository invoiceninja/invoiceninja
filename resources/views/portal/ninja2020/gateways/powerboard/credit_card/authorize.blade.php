@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="instant-payment" content="yes" />
    <meta name="public_key" content="{{ $public_key }}" />
    <meta name="gateway_id" content="{{ $gateway_id }}" />
    <meta name="store_route" content="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" />
    <meta name="environment" content="{{ $environment }}" />
@endsection

@section('gateway_content')
    <form action="javascript:void(0);" id="stepone">
        <input type="hidden" name="gateway_response">
        <button type="submit" class="hidden" id="stepone_submit">Submit</button>
    </form>

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="browser_details">
        <input type="hidden" name="charge">
        <button type="submit" class="hidden" id="stub">Submit</button>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div id="powerboard-payment-container" class="w-full p-4" style="background-color: rgb(249, 249, 249);">
        <div id="widget" style="block"></div>
        <div id="widget-3dsecure"></div>
    </div>  

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-card'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <style>
        iframe {
            border: 0;
            width: 100%;
            height: 400px;
        }
    </style>

    <script src="{{ $widget_endpoint }}"></script>

    @vite('resources/js/clients/payment_methods/authorize-powerboard-card.js')
@endsection



