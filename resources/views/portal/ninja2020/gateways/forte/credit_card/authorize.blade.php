@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    {{-- <meta name="authorize-public-key" content="{{ $public_client_id }}">
    <meta name="authorize-login-id" content="{{ $api_login_id }}"> --}}
    <meta name="year-invalid" content="{{ ctrans('texts.year_invalid') }}">
    <meta name="month-invalid" content="{{ ctrans('texts.month_invalid') }}">
    <meta name="credit-card-invalid" content="{{ ctrans('texts.credit_card_invalid') }}">

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>

    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
          method="post" id="server_response">
        @csrf

        {{-- <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}"> --}}
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
        <input type="hidden" name="expiry_month" id="expiration_month">
        <input type="hidden" name="expiry_year" id="expiration_year">
        <input type="hidden" name="card_type" id="card_type">

        @if(!Request::isSecure())
            <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
        @endif

        
        @if(Session::has('error'))
            <div class="alert alert-failure mb-4" id="errors">{{ Session::get('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-failure mb-4">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
            {{ ctrans('texts.credit_card') }}
        @endcomponent

        @include('portal.ninja2020.gateways.forte.includes.credit_card')

        <div class="bg-white px-4 py-5 flex justify-end">
            <button type="button"
                onclick="submitCard()"
                class="button button-primary bg-primary {{ $class ?? '' }}">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                <span>{{ $slot ?? ctrans('texts.add_payment_method') }}</span>
            </button>
            <input type="submit" style="display: none" id="form_btn">
        </div>
        
    </form>

    {{-- @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'card_button'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent --}}
@endsection

@section('gateway_footer')
    {{-- @if($gateway->company_gateway->getConfigField('testMode'))
        <script src="https://jstest.authorize.net/v1/Accept.js" charset="utf-8"></script>
    @else
        <script src="https://js.authorize.net/v1/Accept.js" charset="utf-8"></script>
    @endif

    <script src="{{ asset('js/clients/payment_methods/authorize-authorize-card.js') }}"></script> --}}
    <script>
        function submitCard(){
            var doc = document.getElementsByClassName("card-number-wrapper");
            var cardType=doc[0].childNodes[1].classList[2];
            if (cardType=='master-card') {
                document.getElementById('card_type').value='mast';
            } else if(cardType=='visa') {
                document.getElementById('card_type').value='visa';
            }else if(cardType=='jcb') {
                document.getElementById('card_type').value='jcb';
            }else if(cardType=='discover') {
                document.getElementById('card_type').value='disc';
            }else if(cardType=='american-express') {
                document.getElementById('card_type').value='amex';
            }else{
                document.getElementById('card_type').value=cardType;
            }
            var month=document.querySelector('input[name=expiry-month]').value;
            var year=document.querySelector('input[name=expiry-year]').value;
            document.getElementById('expiration_month').value=month;
            document.getElementById('expiration_year').value=year;
            let button = document.querySelector("#form_btn");
            button.click();
        }
    </script>
@endsection