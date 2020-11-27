@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="customer-email" content="{{ $customer_email }}">
    <meta name="value" content="{{ $value }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="reference" content="{{ $payment_hash }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="reference" value="{{ $payment_hash }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="value" value="{{ $value }}">
        <input type="hidden" name="raw_value" value="{{ $raw_value }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="pay_with_token" value="false">
        
        @isset($token)
            <input type="hidden" name="token" value="{{ $token->token }}">
        @endisset
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }} (Checkout.com)
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <label class="mr-4">
            <input 
                type="radio" 
                id="toggle-payment-with-token" 
                class="form-radio cursor-pointer" name="payment-type" />
            <span class="ml-1 cursor-pointer">**** {{ $token->meta->last4 }}</span>
        </label>
        <label>
            <input 
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type" 
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.credit_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <form class="payment-form" method="POST" action="#" id="checkout--container">
            @if(app()->environment() == 'production')
                <script async src="https://cdn.checkout.com/js/checkout.js"></script>
            @else
                <script async src="https://cdn.checkout.com/sandbox/js/checkout.js"></script>
            @endif
        </form>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
       <div class="hidden" id="pay-now-with-token--container">
            @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token'])
       </div>
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="{{ asset('js/clients/payments/checkout.com.js') }}"></script>
@endsection