@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_content')
<div id="braintree-ach-payment">
    @if(count($tokens) > 0)
        <div class="alert alert-failure mb-4" hidden id="errors"></div>

        @include('portal.ninja2020.gateways.includes.payment_details')

        <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
            @csrf
            <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
            <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
            <input type="hidden" name="source" value="">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="currency" value="{{ $currency }}">
            <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        </form>

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="payment-method-list">
            @if(count($tokens) > 0)
                @foreach($tokens as $token)
                <li class="payment-method-item">
                <label class="payment-method-label">
                <input
                            type="radio"
                            data-token="{{ $token->hashed_id }}"
                            name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token"/>
                        <span class="ml-1">{{ ctrans('texts.bank_transfer') }} (*{{ $token->meta->last4 }})</span>
                    </label>
                </li>
                @endforeach
            @endif
                </ul>
    @endcomponent

        @include('portal.ninja2020.gateways.includes.pay_now')

    @else
        @component('portal.ninja2020.components.general.card-element-single', ['title' => 'ACH', 'show_title' => false])
            <span class="block">{{ ctrans('texts.bank_account_not_linked') }}</span>
            <a class="hover:underline text-primary mt-2 block"
               href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method') }}</a>
        @endcomponent
    @endif
</div>
@endsection

@section('gateway_footer')
    @vite('resources/js/clients/payment_methods/braintree-ach.js')
@endsection
