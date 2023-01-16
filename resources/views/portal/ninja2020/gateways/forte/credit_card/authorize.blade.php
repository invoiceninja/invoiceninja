@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="year-invalid" content="{{ ctrans('texts.year_invalid') }}">
    <meta name="month-invalid" content="{{ ctrans('texts.month_invalid') }}">
    <meta name="credit-card-invalid" content="{{ ctrans('texts.credit_card_invalid') }}">

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/forte-card-js.min.js') }}"></script>

    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
    @if($gateway->company_gateway->getConfigField('testMode'))
        <script type="text/javascript" src="https://sandbox.forte.net/api/js/v1"></script>
    @else
        <script type="text/javascript" src="https://api.forte.net/js/v1"></script>
    @endif
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="one_time_token" id="one_time_token">
        <input type="hidden" name="card_type" id="card_type">
        <input type="hidden" name="expire_year" id="expire_year">
        <input type="hidden" name="expire_month" id="expire_month">
        <input type="hidden" name="last_4" id="last_4">

        @if(!Request::isSecure())
            <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
        @endif

        
        @if(Session::has('error'))
            <div class="alert alert-failure mb-4" id="errors">{{ Session::get('error') }}</div>
        @endif
        <div id="forte_errors"></div>
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
@endsection

@section('gateway_footer')
    <script>
        function onTokenCreated(params) {
            document.getElementById('one_time_token').value=params.onetime_token;
            document.getElementById('last_4').value=params.last_4;
            let button = document.querySelector("#form_btn");
            button.click();
        }
        function onTokenFailed(params) {
            var errors = '<div class="alert alert-failure mb-4"><ul><li>'+ params.response_description +'</li></ul></div>';
            document.getElementById("forte_errors").innerHTML = errors;
        }
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
            var cc=document.getElementById('card_number').value.replaceAll(' ','');
            var cvv=document.getElementById('cvv').value;
            
            document.getElementById('expire_year').value=year;
            document.getElementById('expire_month').value=month;
            
            var data = {
               api_login_id: '{{$gateway->company_gateway->getConfigField("apiLoginId")}}',
               card_number: cc,
               expire_year: year, 
               expire_month: month,
               cvv: cvv,
            }

            forte.createToken(data)
               .success(onTokenCreated)
               .error(onTokenFailed);
            return false;
        }
    </script>
@endsection