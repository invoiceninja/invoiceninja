@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="helcim-token" content="{{ $gateway->company_gateway->getConfigField('apiToken') }}">
    <meta name="helcim-test-mode" content="{{ $gateway->company_gateway->getConfigField('testMode') ? 'true' : 'false' }}">
    <meta name="amount" content="{{ $amount }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="store_card" id="store_card">
        <input type="hidden" name="token" id="token">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if (count($tokens) > 0)
            @foreach ($tokens as $token)
                <label class="mr-4">
                    <input type="radio" data-token="{{ $token->hashed_id }}" name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token" />
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endif
        
        <label>
            <input type="radio" id="toggle-payment-with-credit-card" class="form-radio cursor-pointer" name="payment-type" checked />
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="helcim-card-container"></div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script src="https://myhelcim.com/js/version2.js"></script>
    @vite('resources/js/clients/payments/helcim-credit-card.js')
@endsection