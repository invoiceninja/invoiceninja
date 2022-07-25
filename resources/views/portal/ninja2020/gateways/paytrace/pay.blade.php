@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="paytrace-client-key" content="{{ $client_key }}">
    <meta name="ctrans-cvv" content="{{ ctrans('texts.cvv') }}">
    <meta name="ctrans-card_number" content="{{ ctrans('texts.card_number') }}">
    <meta name="ctrans-expires" content="{{ ctrans('texts.expires') }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="token" id="token" />
        <input type="hidden" name="store_card" id="store_card" />
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}" />
        <input type="txt" id="HPF_Token" name="HPF_Token" hidden>
        <input type="txt" id="enc_key" name="enc_key" hidden>
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
                    <span class="ml-1 cursor-pointer">{{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endisset
        
        <label>
            <input type="radio" id="toggle-payment-with-credit-card" class="form-radio cursor-pointer" name="payment-type"
                checked />
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="items-center" id="paytrace--credit-card-container">
            <div id="pt_hpf_form"></div>
        </div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script src='https://protect.paytrace.com/js/protect.min.js'></script>
    <script src="{{ asset('js/clients/payments/paytrace-credit-card.js') }}"></script>
@endsection
