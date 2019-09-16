@extends('portal.default.gateways.authorize')

@section('credit_card')

<div class="py-md-5 ninja stripe">
    <div class="form-group">

        <input class="form-control" id="cardholder-name" type="text" placeholder="{{ ctrans('texts.name') }}">
        <!-- placeholder for Elements -->
        <div id="card-element" class="form-control"></div>
        <button id="card-button" class="btn btn-primary pull-right" data-secret="{{ $intent->client_secret }}">
          {{ ctrans('texts.save') }}
        </button>
    </div>
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
.StripeElement {
  box-sizing: border-box;

  height: 40px;

  padding: 10px 12px;

  border: 1px solid transparent;
  border-radius: 4px;
  background-color: white;

  box-shadow: 0 1px 3px 0 #e6ebf1;
  -webkit-transition: box-shadow 150ms ease;
  transition: box-shadow 150ms ease;
}

.StripeElement--focus {
  box-shadow: 0 1px 3px 0 #cfd7df;
}

.StripeElement--invalid {
  border-color: #fa755a;
}

.StripeElement--webkit-autofill {
  background-color: #fefde5 !important;
}

</style>
 
@endpush