@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="instant-payment" content="yes" />
    <meta name="public_key" content="{{ $public_key }}" />
    <meta name="gateway_id" content="{{ $gateway_id }}" />
    <meta name="environment" content="{{ $environment }}">
    <meta name="payments_route" content="{{ route('client.payments.response') }}" />
    <meta name="supported_cards" content="{{ json_encode($supported_cards) }}" />
@endsection

@section('gateway_content')

    <form action="javascript:void(0);" id="stepone">
        <input type="hidden" name="gateway_response">
        <button type="submit" class="hidden" id="stepone_submit">Submit</button>
    </form>

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
        <input type="hidden" name="browser_details">
        <input type="hidden" name="charge">
        <input type="hidden" name="charge_3ds_id">
        <button type="submit" class="hidden" id="stub">Submit</button>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="list-none">
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
            <li class="py-2 cursor-pointer">
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            </li>
            @endforeach
        @endisset

            <li class="py-2 cursor-pointer">
                <label>
                    <input
                        type="radio"
                        id="toggle-payment-with-credit-card"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer"
                        name="payment-type"
                        checked/>
                    <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
                </label>
            </li>    
        </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    <div id="powerboard-payment-container" class="w-full p-4 hidden" style="background-color: rgb(249, 249, 249);">
        <div id="widget" style="block" class="hidden"></div>
        <div id="widget-3dsecure"></div>
    </div>

    @include('portal.ninja2020.gateways.includes.pay_now')
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
    
    @vite('resources/js/clients/payments/powerboard-credit-card.js')
@endsection



