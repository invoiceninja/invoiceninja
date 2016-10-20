@extends('payments.credit_card')

@section('head')
    @parent

    <script type="text/javascript" src="https://js.braintreegateway.com/js/braintree-2.23.0.min.js"></script>
    <script type="text/javascript" >
        $(function() {
            var $form = $('.payment-form');
            braintree.setup("{{ $transactionToken }}", "custom", {
                id: "payment-form",
                hostedFields: {
                    number: {
                        selector: "#card_number",
                        placeholder: "{{ trans('texts.card_number') }}"
                    },
                    cvv: {
                        selector: "#cvv",
                        placeholder: "{{ trans('texts.cvv') }}"
                    },
                    expirationMonth: {
                        selector: "#expiration_month",
                        placeholder: "{{ trans('texts.expiration_month') }}"
                    },
                    expirationYear: {
                        selector: "#expiration_year",
                        placeholder: "{{ trans('texts.expiration_year') }}"
                    },
                    styles: {
                        'input': {
                            'font-family': {!!  json_encode(Utils::getFromCache($account->getBodyFontId(), 'fonts')['css_stack']) !!},
                            'font-weight': "{{ Utils::getFromCache($account->getBodyFontId(), 'fonts')['css_weight'] }}",
                            'font-size': '16px'
                        }
                    }
                },
                onError: function(e) {
                    $form.find('button').prop('disabled', false);
                    // Show the errors on the form
                    if (e.details && e.details.invalidFieldKeys.length) {
                        var invalidField = e.details.invalidFieldKeys[0];

                        if (invalidField == 'number') {
                            $('#js-error-message').html('{{ trans('texts.invalid_card_number') }}').fadeIn();
                        }
                        else if (invalidField == 'expirationDate' || invalidField == 'expirationYear' || invalidField == 'expirationMonth') {
                            $('#js-error-message').html('{{ trans('texts.invalid_expiry') }}').fadeIn();
                        }
                        else if (invalidField == 'cvv') {
                            $('#js-error-message').html('{{ trans('texts.invalid_cvv') }}').fadeIn();
                        }
                    }
                    else {
                        $('#js-error-message').html(e.message).fadeIn();
                    }
                },
                onPaymentMethodReceived: function(e) {
                    // Insert the token into the form so it gets submitted to the server
                    $form.append($('<input type="hidden" name="sourceToken"/>').val(e.nonce));
                    // and submit
                    $form.get(0).submit();
                }
            });
            $('.payment-form').submit(function(event) {
                var $form = $(this);

                // Disable the submit button to prevent repeated clicks
                $form.find('button').prop('disabled', true);
                $('#js-error-message').hide();
            });
        });
    </script>
@stop
