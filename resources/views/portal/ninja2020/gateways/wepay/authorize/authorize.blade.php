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
    WePay.set_endpoint("stage"); // change to "production" when live

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

    // Attach the event to the DOM
    addEvent(d.id('card_button'), 'click', function() {
        var userName = [valueById('cardholder_name')].join(' ');
            response = WePay.credit_card.create({
            "client_id":        valueById('client_id'),
            "user_name":        valueById('cardholder_name'),
            "email":            valueById('email'),
            "cc_number":        valueById('card-number'),
            "cvv":              valueById('cvv'),
            "expiration_month": valueById('expiration_month'),
            "expiration_year":  valueById('expiration_year'),
            "address": {
                "postal_code": valueById('postal_code')
            }
        }, function(data) {
            if (data.error) {
                console.log(data);
                // handle error response
            } else {
                // call your own app's API to save the token inside the data;
                // show a success page
            }
        });
    });

})();
</script>

@endsection