@extends('payments.credit_card')

@section('head')
    @parent

    <style type="text/css">
    .paymill-form {
        margin-left: 16px;
    }
    </style>


    <script type="text/javascript">
    var PAYMILL_PUBLIC_KEY = '{{ $accountGateway->getPublishableKey() }}';
    </script>

    <script type="text/javascript" src = "https://bridge.paymill.com/"></script>
    <script type="text/javascript">

    $(function() {
        var options = {
            lang: '{{ App::getLocale() }}',
            resize: false,
        };

        var callback = function(error){
            if (error){
                console.log(error.apierror, error.message);
            } else {

            }
        };

        paymill.embedFrame('paymillCardFields', options, callback);

        $('.payment-form').unbind('submit').submit(function(event) {
            if ($('#sourceToken').val()) {
                // do nothing
            } else {
                event.preventDefault();

                var data = {
                    amount_int: {{ $invitation->invoice->getRequestedAmount() * 100 }},
                    currency: '{{ $invitation->invoice->getCurrencyCode() }}',
                    email: '{{ $contact->email }}',
                };

                var callback = function(error, result) {
                    if (error) {
                        if (error.apierror == 'field_invalid_card_number') {
                            var message = "{{ trans('texts.invalid_card_number') }}";
                        } else {
                            var message = error.apierror;
                            if (error.message) {
                                message += ': ' + error.message;
                            }
                        }
                        $('.payment-form').find('button').prop('disabled', false);
                        $('#js-error-message').html(message).fadeIn();
                    } else {
                        $('#sourceToken').val(result.token);
                        $('.payment-form').submit();
                    }
                }

                if ($('.payment-form').find('button').is(':disabled')) {
                    return false;
                }

                // Disable the submit button to prevent repeated clicks
                $('.payment-form').find('button').prop('disabled', true);
                $('#js-error-message').hide();

                paymill.createTokenViaFrame(data, callback);
            }
        });
    })

    </script>

@stop
