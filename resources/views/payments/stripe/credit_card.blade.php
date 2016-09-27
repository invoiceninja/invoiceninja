@extends('payments.credit_card')

@section('head')
    @parent

    @if ($accountGateway->getPublishableStripeKey())
        <script type="text/javascript" src="https://js.stripe.com/v2/"></script>
        <script type="text/javascript">
            Stripe.setPublishableKey('{{ $accountGateway->getPublishableStripeKey() }}');
            $(function() {
                var countries = {!! Cache::get('countries')->pluck('iso_3166_2','id') !!};
                $('.payment-form').unbind('submit').submit(function(event) {
                    if($('[name=plaidAccountId]').length)return;

                    var $form = $(this);

                    var data = {
                        name: $('#first_name').val() + ' ' + $('#last_name').val(),
                        address_line1: $('#address1').val(),
                        address_line2: $('#address2').val(),
                        address_city: $('#city').val(),
                        address_state: $('#state').val(),
                        address_zip: $('#postal_code').val(),
                        address_country: $("#country_id option:selected").text(),
                        number: $('#card_number').val(),
                        //cvc: $('#cvv').val(),
                        exp_month: $('#expiration_month').val(),
                        exp_year: $('#expiration_year').val()
                    };

                    // Validate the card details
                    if (!Stripe.card.validateCardNumber(data.number)) {
                        $('#js-error-message').html('{{ trans('texts.invalid_card_number') }}').fadeIn();
                        return false;
                    }
                    if (!Stripe.card.validateExpiry(data.exp_month, data.exp_year)) {
                        $('#js-error-message').html('{{ trans('texts.invalid_expiry') }}').fadeIn();
                        return false;
                    }

                    if ($('#cvv').val() != ' ') {
                        data.cvc = $('#cvv').val();
                        if (!Stripe.card.validateCVC(data.cvc)) {
                            $('#js-error-message').html('{{ trans('texts.invalid_cvv') }}').fadeIn();
                            return false;
                        }
                    }

                    // Disable the submit button to prevent repeated clicks
                    $form.find('button').prop('disabled', true);
                    $('#js-error-message').hide();

                    Stripe.card.createToken(data, stripeResponseHandler);

                    // Prevent the form from submitting with the default action
                    return false;
                });

            });

            function stripeResponseHandler(status, response) {
                var $form = $('.payment-form');

                if (response.error) {
                    // Show the errors on the form
                    var error = response.error.message;
                    $form.find('button').prop('disabled', false);
                    $('#js-error-message').html(error).fadeIn();
                } else {
                    // response contains id and card, which contains additional card details
                    var token = response.id;
                    if (token) {
                        // Insert the token into the form so it gets submitted to the server
                        $form.append($('<input type="hidden" name="sourceToken"/>').val(token));
                        // and submit
                        $form.get(0).submit();
                    } else {
                        $('#js-error-message').html('An error occurred').fadeIn();
                        logError('STRIPE_ERROR:' + JSON.stringify(response));
                    }
                }
            };
        </script>
    @endif
@stop
