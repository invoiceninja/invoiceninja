@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="lawpay-public-key" content="{{ $gateway->company_gateway->getConfigField('publicKey') }}">
    <meta name="instant-payment" content="yes" />
    <script src="https://cdn.affinipay.com/hostedfields/1.0/fieldGen.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="card_brand" id="card_brand">
        <input type="hidden" name="exp_month" id="exp_month">
        <input type="hidden" name="exp_year" id="exp_year">
        <input type="hidden" name="last_4" id="last_4">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="token" id="token" />
        <input type="hidden" name="store_card" id="store_card" />
        <input type="submit" style="display: none" id="form_btn">
    </form>

    <div id="lawpay_errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
    <ul class="list-none">
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
            <li class="py-2">
                <label class="mr-4 cursor-pointer">
                    <input
                        type="radio"
                        data-token="{{ $token->hashed_id }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            </li>
            @endforeach
        @endisset

        <li class="py-2">
            <label class="mr-4 cursor-pointer">
                <input
                    type="radio"
                    id="toggle-payment-with-credit-card"
                    class="form-radio cursor-pointer"
                    name="payment-type"
                    checked/>
                <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
            </label>
        </li>
    </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')
    @include('portal.ninja2020.gateways.lawpay.includes.credit_card')
    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    @vite('resources/js/clients/payments/lawpay-credit-card-payment.js')
@endsection
