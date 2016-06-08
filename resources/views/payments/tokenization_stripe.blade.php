<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
<script type="text/javascript">
    Stripe.setPublishableKey('{{ $accountGateway->getPublishableStripeKey() }}');
    $(function() {
        var countries = {!! $countries->pluck('iso_3166_2','id') !!};
        $('.payment-form').submit(function(event) {
            if($('[name=plaidAccountId]').length)return;

            var $form = $(this);

            var data = {
                @if($paymentType == PAYMENT_TYPE_STRIPE_ACH)
                account_holder_name: $('#account_holder_name').val(),
                account_holder_type: $('[name=account_holder_type]:checked').val(),
                currency: $("#currency").val(),
                country: countries[$("#country_id").val()],
                routing_number: $('#routing_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, ''),
                account_number: $('#account_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '')
                @else
                name: $('#first_name').val() + ' ' + $('#last_name').val(),
                address_line1: $('#address1').val(),
                address_line2: $('#address2').val(),
                address_city: $('#city').val(),
                address_state: $('#state').val(),
                address_zip: $('#postal_code').val(),
                address_country: $("#country_id option:selected").text(),
                number: $('#card_number').val(),
                cvc: $('#cvv').val(),
                exp_month: $('#expiration_month').val(),
                exp_year: $('#expiration_year').val()
                @endif
            };

            @if($paymentType == PAYMENT_TYPE_STRIPE_ACH)
            // Validate the account details
            if (!data.account_holder_type) {
                $('#js-error-message').html('{{ trans('texts.missing_account_holder_type') }}').fadeIn();
                return false;
            }
            if (!data.account_holder_name) {
                $('#js-error-message').html('{{ trans('texts.missing_account_holder_name') }}').fadeIn();
                return false;
            }
            if (!data.routing_number || !Stripe.bankAccount.validateRoutingNumber(data.routing_number, data.country)) {
                $('#js-error-message').html('{{ trans('texts.invalid_routing_number') }}').fadeIn();
                return false;
            }
            if (data.account_number != $('#confirm_account_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '')) {
                $('#js-error-message').html('{{ trans('texts.account_number_mismatch') }}').fadeIn();
                return false;
            }
            if (!data.account_number || !Stripe.bankAccount.validateAccountNumber(data.account_number, data.country)) {
                $('#js-error-message').html('{{ trans('texts.invalid_account_number') }}').fadeIn();
                return false;
            }
            @else
            // Validate the card details
            if (!Stripe.card.validateCardNumber(data.number)) {
                $('#js-error-message').html('{{ trans('texts.invalid_card_number') }}').fadeIn();
                return false;
            }
            if (!Stripe.card.validateExpiry(data.exp_month, data.exp_year)) {
                $('#js-error-message').html('{{ trans('texts.invalid_expiry') }}').fadeIn();
                return false;
            }
            if (!Stripe.card.validateCVC(data.cvc)) {
                $('#js-error-message').html('{{ trans('texts.invalid_cvv') }}').fadeIn();
                return false;
            }
            @endif

            // Disable the submit button to prevent repeated clicks
            $form.find('button').prop('disabled', true);
            $('#js-error-message').hide();

            @if($paymentType == PAYMENT_TYPE_STRIPE_ACH)
            Stripe.bankAccount.createToken(data, stripeResponseHandler);
            @else
            Stripe.card.createToken(data, stripeResponseHandler);
            @endif

            // Prevent the form from submitting with the default action
            return false;
        });

                @if($accountGateway->getPlaidEnabled())
        var plaidHandler = Plaid.create({
                    selectAccount: true,
                    env: '{{ $accountGateway->getPlaidEnvironment() }}',
                    clientName: {!! json_encode($account->getDisplayName()) !!},
                    key: '{{ $accountGateway->getPlaidPublicKey() }}',
                    product: 'auth',
                    onSuccess: plaidSuccessHandler,
                    onExit : function(){$('#secured_by_plaid').hide()}
                });

        $('#plaid_link_button').click(function(){plaidHandler.open();$('#secured_by_plaid').fadeIn()});
        $('#plaid_unlink').click(function(e){
            e.preventDefault();
            $('#manual_container').fadeIn();
            $('#plaid_linked').hide();
            $('#plaid_link_button').show();
            $('#pay_now_button').hide();
            $('#add_account_button').show();
            $('[name=plaidPublicToken]').remove();
            $('[name=plaidAccountId]').remove();
            $('[name=account_holder_type],#account_holder_name').attr('required','required');
        })
        @endif
    });

    function stripeResponseHandler(status, response) {
        var $form = $('.payment-form');

        if (response.error) {
            // Show the errors on the form
            var error = response.error.message;
            @if($paymentType == PAYMENT_TYPE_STRIPE_ACH)
            if(response.error.param == 'bank_account[country]') {
                error = "{{trans('texts.country_not_supported')}}";
            }
            @endif
            $form.find('button').prop('disabled', false);
            $('#js-error-message').html(error).fadeIn();
        } else {
            // response contains id and card, which contains additional card details
            var token = response.id;
            // Insert the token into the form so it gets submitted to the server
            $form.append($('<input type="hidden" name="sourceToken"/>').val(token));
            // and submit
            $form.get(0).submit();
        }
    };

    function plaidSuccessHandler(public_token, metadata) {
        $('#secured_by_plaid').hide()
        var $form = $('.payment-form');

        $form.append($('<input type="hidden" name="plaidPublicToken"/>').val(public_token));
        $form.append($('<input type="hidden" name="plaidAccountId"/>').val(metadata.account_id));
        $('#plaid_linked_status').text('{{ trans('texts.plaid_linked_status') }}'.replace(':bank', metadata.institution.name));
        $('#manual_container').fadeOut();
        $('#plaid_linked').show();
        $('#plaid_link_button').hide();
        $('[name=account_holder_type],#account_holder_name').removeAttr('required');


        var payNowBtn = $('#pay_now_button');
        if(payNowBtn.length) {
            payNowBtn.show();
            $('#add_account_button').hide();
        }
    };
</script>