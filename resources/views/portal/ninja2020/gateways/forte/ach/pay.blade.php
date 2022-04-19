@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Transfer', 'card_title' => 'Bank Transfer'])

@section('gateway_head')
    {{-- <meta name="authorize-public-key" content="{{ $public_client_id }}">
    <meta name="authorize-login-id" content="{{ $api_login_id }}"> --}}

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>

    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->forte->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{$payment_method_id}}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
        <input type="hidden" name="token" id="token"/>
        <input type="hidden" name="store_card" id="store_card"/>

        <div id="errors"></div>

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
            Bank Transfer
        @endcomponent

        @include('portal.ninja2020.gateways.includes.payment_details')

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            @if(count($tokens) > 0)
                @foreach($tokens as $token)
                    <label class="mr-4">
                        <input
                            type="radio"
                            data-token="{{ $token->hashed_id }}"
                            name="payment_token"
                            id="payment_token"
                            value="{{ $token->token }}"
                            class="form-radio cursor-pointer toggle-payment-with-token"/>
                        <span class="ml-1 cursor-pointer">**** {{ optional($token->meta)->last4 }}</span>
                    </label>
                @endforeach
            @else
                <div class="relative" x-data="{ open: false }" x-on:click.away="open = false">
                    @if($client->getBankTransferGateway())
                        <a data-cy="add-bank-account-link" href="{{ route('client.payment_methods.create', ['method' => $client->getBankTransferMethodType()]) }}" class="button button-primary bg-primary">
                            {{ ctrans('texts.bank_account') }}
                        </a>
                    @endif
                </div>
            @endif

        @endcomponent
        <div class="bg-white px-4 py-5 flex justify-end">
            <button type="button"
            onclick="submitPay()"
                class="button button-primary bg-primary {{ $class ?? '' }}">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                <span>{{ $slot ?? ctrans('texts.pay_now') }}</span>
                <input type="submit" style="display: none" id="form_btn">
            </button>
        </div>
    </form>

@endsection

@section('gateway_footer')
    <script>
        function submitPay(){
            if ($("input:radio[name='payment_token']").is(":checked") == true) {
            let button = document.querySelector("#form_btn");
                button.click();
            }else{
                document.getElementById('errors').innerHTML='<div class="alert alert-failure mb-4">Please select payemnt method</div>'
            }
        }
    </script>
@endsection
