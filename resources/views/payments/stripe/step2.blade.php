@extends('payments.stripe.credit_card')

@section('head')
    @parent

    <script type="text/javascript">
        // Create a Stripe client.
        var stripe = Stripe('{{ $accountGateway->getPublishableKey() }}', {locale: "{{$client->language?$client->language->locale:$client->account->language->locale}}"});

        stripe.handleCardAction("{{$step2_details['payment_intent']->client_secret}}"
        ).then(function (result) {
            if (result.error) {
                // Inform the user if there was an error.
                var errorElement = document.getElementById('card-errors');
                errorElement.textContent = result.error.message;
            } else {
                // Insert the token ID into the form so it gets submitted to the server
                var form = document.getElementById('payment-form');
                var hiddenInput = document.createElement('input');
                hiddenInput.setAttribute('type', 'hidden');
                hiddenInput.setAttribute('name', 'paymentIntentID');
                hiddenInput.setAttribute('value', result.paymentIntent.id);
                form.appendChild(hiddenInput);

                // Submit the form
                form.submit();
            }
        });
    </script>
@stop
