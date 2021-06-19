@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>
    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="1">

        <input type="hidden" name="token">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ optional($token->meta)->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label>
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @include('portal.ninja2020.gateways.wepay.includes.credit_card')
   
    @include('portal.ninja2020.gateways.includes.pay_now')
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
        
    /* handle the switch between token and cc */
    Array
        .from(document.getElementsByClassName('toggle-payment-with-token'))
        .forEach((element) => element.addEventListener('click', (e) => {
            document
                .getElementById('save-card--container').style.display = 'none';
            document
                .getElementById('wepay--credit-card-container').style.display = 'none';

            document
                .getElementById('token').value = e.target.dataset.token;
        }));

    let payWithCreditCardToggle = document.getElementById('toggle-payment-with-credit-card');

    if (payWithCreditCardToggle) {
        payWithCreditCardToggle
            .addEventListener('click', () => {
                document
                    .getElementById('save-card--container').style.display = 'grid';
                document
                    .getElementById('wepay--credit-card-container').style.display = 'flex';

                document
                    .getElementById('token').value = null;
            });
    }
    /* handle the switch between token and cc */

    /* Attach store card value to form */
    let storeCard = document.querySelector('input[name=token-billing-checkbox]:checked');

    if (storeCard) {
        document.getElementById("store_card").value = storeCard.value;
    }
    /* Attach store card value to form */

    /* Pay Now Button */
    let payNowButton = document.getElementById('pay-now');

    if (payNowButton) {
        payNowButton
            .addEventListener('click', (e) => {
                let token = document.getElementById('token').value;

                if(token){
                    handleTokenPayment($token)
                }
                else{
                    handleCardPayment();
                }
            });
                
    }
    /* Pay Now Button */




    function handleTokenPayment($token)
    {

        document.querySelector('input[name="credit_card_id"]').value = token;                      
        document.getElementById('server_response').submit();

    }







    // Attach the event to the DOM
    function handleCardPayment(){

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
    }

})();
</script>
@endsection
