@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' =>
ctrans('texts.credit_card')])

    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
@section('gateway_head')

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('build/public/js/card-js.min.js/card-js.min.js') }}"></script>
    <link href="{{ asset('build/public/css/card-js.min.css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf

        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="customer" id="customer" value="{{ $customer }}">
        <input type="hidden" name="type" id="type" value="{{ $type ?? 'card'}}">
        <input type="hidden" name="payment_intent" id="payment_intent" value="{{$payment_intent}}">
    

    <!-- <div class="alert alert-failure mb-4" hidden id="errors"></div> -->

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if (count($tokens) > 0)
            @foreach ($tokens as $token)
                <label class="mr-4">
                    <input type="radio" onclick="toggleCard()" value="{{ $token->token }}" data-token="{{ $token->token }}" name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token" />
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endif

        <label>
            <input type="radio" id="toggle-payment-with-credit-card" class="form-radio cursor-pointer" name="payment-type" onclick="toggleCard()" name="new_card" checked />
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>

    @endcomponent
        <input type="hidden" value="0" name="save_card">
        
    <div class="bg-white px-4 py-5 flex" id="toggle-card"> 

       <!--  <button
            type="button"
            id="test"
            class="button button-primary bg-primary">
            <span>{{ "New card" }}</span>
        </button> -->
        @include('portal.ninja2020.gateways.easymerchant.includes.credit_card', ["is_required" => ''])
        
        <!-- @component('portal.ninja2020.components.general.card-element', ['title' => "Save Card"])
        <label class="mr-4">
            <input class="form-radio cursor-pointer ml-1" type="radio" value="1" name="save_card">Yes
            <input class="form-radio cursor-pointer ml-1" type="radio" value="0" name="save_card" checked>No
        </label>
        
        @endcomponent -->
    </div>
    </form>
        <span id="error_message" style="margin-left: 3rem;font-size: 12px;"></span>
    <div class="bg-white px-4 py-5 flex justify-end">
        <button
            type="button"
            id="{{ $id ?? 'pay-now' }}"
            class="button button-primary bg-primary {{ $class ?? '' }}">
            <span id='clickk'>{{ ctrans('texts.pay_now') }}</span>
        </button>
    </div>
    <!-- </form> -->
@endsection

@section('gateway_footer')
    <!-- <script src="https://js.stripe.com/v3/"></script> -->
    <!-- @vite('resources/js/clients/payments/stripe-credit-card.js') -->

@endsection
<script type="text/javascript" src="https://code.jquery.com/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="http://ajax.aspnetcdn.com/ajax/jquery.validate/1.7/jquery.validate.min.js"></script>

<script type="text/javascript">
function toggleCard() {
      var switch_card = document.getElementById('toggle-card');
      var card_number = document.getElementById('card_number');
      var expiration_year = document.getElementById('expiration_year');
      var expiration_month = document.getElementById('expiration_month');
      var card = document.querySelector('input[name="payment-type"]:checked').value;

      if (card === "on") {
        switch_card.style.display = 'block';
        card_number.setAttribute('required', '');
        expiration_month.setAttribute('required', '');
        expiration_year.setAttribute('required', '');
      } else {
        switch_card.style.display = 'none';
        card_number.removeAttribute('required');
        expiration_month.removeAttribute('required');
        expiration_year.removeAttribute('required');
      }
    }
$(document).ready(function(){
    toggleCard();
    $('#pay-now').click(function(){
        $('#error_message').text('')
        var switch_card = document.getElementById('toggle-card');
        var card_number = document.getElementById('card_number').value;
        var expiration_year = document.querySelector('input[name="expiry-year"]').value;
        var expiration_month = document.querySelector('input[name="expiry-month"]').value;
        var card = document.querySelector('input[name="payment-type"]:checked').value;
        var name = document.querySelector('input[name="card-holders-name"]').value;
        var cvv = document.querySelector('input[name="cvc"]').value;

        if(card == 'on'){

            var errors = [];
            if(!card_number){
                errors.push('Card number');
            }
            if(!name){
                errors.push('Cardholder name');
            }
            if(!expiration_year){
                errors.push('Expiry Year');
            }
            if(!expiration_month){
                errors.push('Expiry Month');
            }
            if(!cvv){
                errors.push('CVV');
            }
            if(errors.length > 0){
                var message = ' (s) are required.!';
                if(errors.length == 1){
                    message = ' is required.!';
                }
                $('#error_message').text(errors.toString() + message).css({'color':'red', "font-weight":"bold"})
                return false;
            }

            if(card_number.length <= 15){
                $('#error_message').text("Card number must be 16 characters in length.").css({'color':'red', "font-weight":"bold"})
                return false;   
            }

            // var last4 = card_number.substr(-4);
            //             $('#card_number').val(last4)
            // $('#server-response').submit();

            $.ajax({
                headers: {
                    "X-Publishable-Key": "{{ $publish_key }}",
                },
                url : "{{ $url }}",
                data : { 
                    payment_intent : "{{ $payment_intent }}", 
                    card_number: card_number.replace(/\s+/g, ""),
                    cardholder_name: name,
                    exp_month: expiration_month,
                    exp_year: '20' + expiration_year,
                    cvc: cvv,
                    customer: "{{ $customer }}"
                },
                type : 'POST',
                dataType : 'json',
                success : function(data){
                var last4 = card_number.substr(-4);
                    if(data.status){
                        $('#card_number').val(last4)
                    }else{
                        $('#error_message').text(data.message).css({'color':'red', "font-weight":"bold"})
                        return false;
                    }

                    $('#server-response').submit();

                }
            });
        }else{
            $('#payment_intent').val(card);
            $('#server-response').submit();
        }
    })
})
</script>