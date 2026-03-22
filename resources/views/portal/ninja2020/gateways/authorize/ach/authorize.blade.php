@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => ctrans('texts.ach')])


@section('gateway_head')
    <meta name="authorize-public-key" content="{{ $public_client_id }}">
    <meta name="authorize-login-id" content="{{ $api_login_id }}">
    <meta name="instant-payment" content="yes">
@endsection

@section('gateway_content')


    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
    </form>

    <div class="alert alert-failure mb-4" id="errors" hidden></div>

    @include('portal.ninja2020.gateways.authorize.includes.ach_form')

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'card_button'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent

@endsection

@push('footer')
    
@section('gateway_footer')
    @if($gateway->company_gateway->getConfigField('testMode'))
        <script src="https://jstest.authorize.net/v1/Accept.js" charset="utf-8"></script>
    @else
        <script src="https://js.authorize.net/v1/Accept.js" charset="utf-8"></script>
    @endif

    
    @vite('resources/js/clients/payment_methods/authorize-authorize-ach.js')
@endsection
@endpush 