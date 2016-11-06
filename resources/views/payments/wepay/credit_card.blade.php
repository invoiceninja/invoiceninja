@extends('payments.credit_card')

@section('head')
    @parent

    <script type="text/javascript" src="https://static.wepay.com/min/js/tokenization.v2.js"></script>
    <script type="text/javascript">
        $(function() {
            $("#state").attr('maxlength', '3');
            var countries = {!! Cache::get('countries')->pluck('iso_3166_2','id') !!};
            WePay.set_endpoint('{{ WEPAY_ENVIRONMENT }}');
            var $form = $('.payment-form');
            $('.payment-form').submit(function(event) {
                var data = {
                    client_id: {{ WEPAY_CLIENT_ID }},
                    user_name: $('#first_name').val() + ' ' + $('#last_name').val(),
                    email: $('#email').val(),
                    cc_number: $('#card_number').val(),
                    cvv: $('#cvv').val(),
                    expiration_month: $('#expiration_month').val(),
                    expiration_year: $('#expiration_year').val(),
                    address: {
                        address1: $('#address1').val(),
                        address2: $('#address2').val(),
                        city: $('#city').val(),
                        country: countries[$("#country_id").val()]
                    }
                };

                if(data.address.country == 'US') {
                    data.address.zip = $('#postal_code').val();
                } else {
                    data.address.postcode = $('#postal_code').val();
                }
                // Not including state/province, since WePay wants 2-letter codes and users enter the full name

                // Disable the submit button to prevent repeated clicks
                $form.find('button').prop('disabled', true);
                $('#js-error-message').hide();

                var response = WePay.credit_card.create(data, function(response) {
                    if (response.error) {
                        // Show the errors on the form
                        var error = response.error_description;
                        $form.find('button').prop('disabled', false);
                        $('#js-error-message').text(error).fadeIn();
                    } else {
                        // response contains id and card, which contains additional card details
                        var token = response.credit_card_id;
                        // Insert the token into the form so it gets submitted to the server
                        $form.append($('<input type="hidden" name="sourceToken"/>').val(token));
                        // and submit
                        $form.get(0).submit();
                    }
                });

                if (response.error) {
                    // Show the errors on the form
                    var error = response.error_description;
                    $form.find('button').prop('disabled', false);
                    $('#js-error-message').text(error).fadeIn();
                }

                // Prevent the form from submitting with the default action
                return false;
            });
        });
    </script>
@stop
