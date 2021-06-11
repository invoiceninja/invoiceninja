@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>

    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')

<table>
    <tr>
        <td>Name: </td>
        <td><input id="name" type="text"></td>
    </tr>
    <tr>
        <td>Email: </td>
        <td><input id="email" type="text"></td>
    </tr>
    <tr>
        <td>Credit Card Number: </td>
        <td><input id="cc-number" type="text"></td>
    </tr>
    <tr>
        <td>Expiration Month: </td>
        <td><input id="cc-month" type="text"></td>
    </tr>
    <tr>
        <td>Expiration Year: </td>
        <td><input id="cc-year" type="text"></td>
    </tr>
    <tr>
        <td>CVV: </td>
        <td><input id="cc-cvv" type="text"></td>
    </tr>
    <tr>
        <td>Postal Code: </td>
        <td><input id="postal_code" type="text"></td>
    </tr>
    <tr>
        <td></td>
        <td><input type="submit" name="Submit" value="Submit" id="cc-submit"></td>
    </tr>
</table>

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
    addEvent(d.id('cc-submit'), 'click', function() {
        var userName = [valueById('name')].join(' ');
            response = WePay.credit_card.create({
            "client_id":        118711,
            "user_name":        valueById('name'),
            "email":            valueById('email'),
            "cc_number":        valueById('cc-number'),
            "cvv":              valueById('cc-cvv'),
            "expiration_month": valueById('cc-month'),
            "expiration_year":  valueById('cc-year'),
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