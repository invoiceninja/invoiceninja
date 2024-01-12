@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="contact-email" content="{{ $contact->email }}">
    <meta name="client-postal-code" content="{{ $contact->client->postal_code }}">

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>

    <script src="{{ asset('build/public/js/card-js.min.js/card-js.min.js') }}"></script>
    <link href="{{ asset('build/public/css/card-js.min.css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="store_card" value="1">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="type" id="type" value="{{ $type ?? 'card'}}">
        <input type="hidden" name="customer" id="customer" value="{{ $customer }}">
        <input type="hidden" name="payment_intent" id="payment_intent" value="">

    
    @if(!Request::isSecure())
        <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
    @endif

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.easymerchant.includes.credit_card', ['is_required' => 'required'])

    <span id="error_message" style="margin-left: 3rem; font-size: 12px;"></span>
    <div class="bg-white px-4 py-5 flex justify-end">
        <button
            type="button"
            id="pay-now"
            class="button button-primary bg-primary">
            <span>{{ ctrans('texts.add_payment_method') }}</span>
        </button>
    </div>

   </form> 
@endsection

@section('gateway_footer')

@endsection
<script type="text/javascript" src="https://code.jquery.com/jquery-1.7.1.min.js"></script>
<script type="text/javascript">

    $(document).ready(function(){

    $('#pay-now').click(function(){
        $('#error_message').text('')
        var switch_card = document.getElementById('toggle-card');
        var card_number = document.getElementById('card_number').value;
        var expiration_year = document.querySelector('input[name="expiry-year"]').value;
        var expiration_month = document.querySelector('input[name="expiry-month"]').value;
        var name = document.querySelector('input[name="card-holders-name"]').value;
        var cvv = document.querySelector('input[name="cvc"]').value;
        var customer = "{{ $customer }}";


        if(expiration_month.toString().length < 2){
            expiration_month = "0" + expiration_month;
        }

        var params = {
            card_number: card_number.replace(/\s+/g, ""),
            cardholder_name: name,
            exp_month: expiration_month,
            exp_year: "20" + expiration_year,
            cvc: cvv,
            customer: "{{ $customer }}"
        }

        $.ajax({
            headers: {
                "X-Publishable-Key": "{{ $publish_key }}",
            },
            url : "{{ $url }}",
            data : params,
            type : 'POST',
            dataType : 'json',
            success : function(data){
                var last4 = card_number.substr(-4);
                if(data.status){
                    $('#payment_intent').val(data.card_id)
                    $('#card_number').val(last4)
                }else{
                    $('#error_message').text(data.message).css({'color':'red', "font-weight":"bold"})
                    return false;
                }

                $('#server_response').submit();

            }
        });
    })
})
</script>