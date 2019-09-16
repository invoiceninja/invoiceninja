@extends('portal.default.gateways.authorize')

@section('credit_card')

<div class="py-md-5 ninja stripe">
    <input id="cardholder-name" type="text">
    <!-- placeholder for Elements -->
    <div id="card-element"></div>
    <button id="card-button" data-secret="<?= $intent->client_secret ?>">
      Save Card
    </button>
</div>

@endsection
@push('scripts')
<script src="https://js.stripe.com/v3/"></script>

<script type="text/javascript">
    var stripe = Stripe('{{ $gateway->getPublishableKey() }}');

    var elements = stripe.elements();
    var cardElement = elements.create('card');
    cardElement.mount('#card-element');


    var cardholderName = document.getElementById('cardholder-name');
    var cardButton = document.getElementById('card-button');
    var clientSecret = cardButton.dataset.secret;

    cardButton.addEventListener('click', function(ev) {
      stripe.handleCardSetup(
        clientSecret, cardElement, {
          payment_method_data: {
            billing_details: {name: cardholderName.value}
          }
        }
      ).then(function(result) {
        if (result.error) {
          // Display error.message in your UI.
        } else {
          // The setup has succeeded. Display a success message.
        }
      });
    });
</script>

@endpush

@push('css')
<style type="text/css">


</style>
 
@endpush