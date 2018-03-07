@extends('payments.payment_method')

@section('head')
    @parent

    <script type="text/javascript" src="https://js.stripe.com/v3/"></script>
    <script type="text/javascript">
        // https://stripe.com/docs/stripe-js/elements/payment-request-button
        var stripe = Stripe('{{ $accountGateway->getPublishableKey() }}');
        var paymentRequest = stripe.paymentRequest({
            country: '{{ $client->getCountryCode() }}',
            currency: '{{ strtolower($client->getCurrencyCode()) }}',
            total: {
                label: '{{ trans('texts.invoice') . ' ' . $invitation->invoice->invoice_number }}',
                amount: {{ $invitation->invoice->getRequestedAmount() * 100 }},
            },
        });

        var elements = stripe.elements();
        var prButton = elements.create('paymentRequestButton', {
            paymentRequest: paymentRequest,
            style: {
              paymentRequestButton: {
                height: '44px',
              },
            },
        });

        $(function() {
            // Check the availability of the Payment Request API first.
            paymentRequest.canMakePayment().then(function(result) {
                if (result) {
                    prButton.mount('#payment-request-button');
                } else {
                    document.getElementById('payment-request-button').style.display = 'none';
                    document.getElementById('error-message').style.display = 'inline';
                }
            });

            paymentRequest.on('token', function(ev) {
                $.ajax({
                    dataType: 'json',
                    type: 'post',
                    data: {sourceToken: ev.token.id},
                    url: '{{ $invitation->getLink('payment') }}/apple_pay',
                    accepts: {
                        json: 'application/json'
                    },
                    success: function(response) {
                        if (response == '{{ RESULT_SUCCESS }}') {
                            ev.complete('success');
                            location.reload();
                        } else {
                            ev.complete('fail');
                        }
                    },
                    error: function(error) {
                        ev.complete('fail');
                    }
                });
            });

        });

    </script>

@stop

@section('payment_details')

    <center>
        <div id="error-message" style="display:none">{{ trans('texts.apple_pay_not_supported') }}</div>
    </center>

    <p>&nbsp;&nbsp;</p>

    <div class="row">
        <div class="col-md-1 col-md-offset-4">
            {!! Button::normal(strtoupper(trans('texts.cancel')))->large()->asLinkTo($invitation->getLink()) !!}
        </div>
        <div class="col-md-1">
            <div id="payment-request-button" style="padding-left:20px;width:250px;"></div>
        </div>
    </div>


@stop
