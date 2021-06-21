@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
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

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="credit_card_id" id="credit_card_id">
    </form>

    @if(!Request::isSecure())
        <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
    @endif

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.wepay.includes.credit_card')

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'card_button'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')

<script type="text/javascript" src="https://static.wepay.com/min/js/tokenization.4.latest.js"></script>
<script type="text/javascript">
(function() {

    @if(config('ninja.wepay.environment') == 'staging')
    WePay.set_endpoint("stage"); 
    @else
    WePay.set_endpoint("production");
    @endif
    // Shortcuts
    var d = document;
        d.id = d.getElementById,
        valueById = function(id) {
            return d.id(id).value;
        };

    // For those not using DOM libraries
    var addEvent = function(e,v,f) {
        if (!!window.attachEvent) { e.attachEvent('on' + v, f); }
        else { e.addEventListener(v, f, false); }
    };

    let errors = document.getElementById('errors');

    // Attach the event to the DOM
    addEvent(document.getElementById('card_button'), 'click', function() {

        var myCard = $('#my-card');

        if(document.getElementById('cardholder_name') == "") {
            document.getElementById('cardholder_name').focus();
            errors.textContent = "Cardholder name required.";
            errors.hidden = false;
            return;
        }
        else if(myCard.CardJs('cardNumber') == ""){
            document.getElementById('card_number').focus();
            errors.textContent = "Card number required.";
            errors.hidden = false;
            return;
        }
        else if(myCard.CardJs('cvc') == ""){
            document.getElementById('cvv').focus();
            errors.textContent = "CVV number required.";
            errors.hidden = false;
            return;
        }
        else if(myCard.CardJs('expiryMonth') == ""){
            // document.getElementById('expiry_month').focus();
            errors.textContent = "Expiry Month number required.";
            errors.hidden = false;
            return;
        }
        else if(myCard.CardJs('expiryYear') == ""){
            // document.getElementById('expiry_year').focus();
            errors.textContent = "Expiry Year number required.";
            errors.hidden = false;
            return;
        }
 
        cardButton = document.getElementById('card_button');
        cardButton.disabled = true;

        cardButton.querySelector('svg').classList.remove('hidden');
        cardButton.querySelector('span').classList.add('hidden');

        var userName = [valueById('cardholder_name')].join(' ');
            response = WePay.credit_card.create({
            "client_id":        "{{ config('ninja.wepay.client_id') }}",
            "user_name":        valueById('cardholder_name'),
            "email":            "{{ $contact->email }}",
            "cc_number":        myCard.CardJs('cardNumber'),
            "cvv":              myCard.CardJs('cvc'),
            "expiration_month": myCard.CardJs('expiryMonth'),
            "expiration_year":  myCard.CardJs('expiryYear'),
            "address": {
                "postal_code": "{{ $contact->client->postal_code }}"
            }
        }, function(data) {

            if (data.error) {
                //console.log(data);
                // handle error response error_description
                cardButton = document.getElementById('card_button');
                cardButton.disabled = false;
                cardButton.querySelector('svg').classList.add('hidden');
                cardButton.querySelector('span').classList.remove('hidden'); 
                
                errors.textContent = '';
                errors.textContent = data.error_description;
                errors.hidden = false;

            } else {

                var token = data.credit_card_id;

                document.querySelector('input[name="credit_card_id"]').value = token;                      
                document.getElementById('server_response').submit();

            }
        });
    });

})();
</script>

@endsection