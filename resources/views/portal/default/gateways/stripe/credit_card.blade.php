@extends('portal.default.gateways.pay_now')

@section('pay_now')

{!! Former::framework('TwitterBootstrap4'); !!}

{!! Former::horizontal_open()
      ->id('server_response')
      ->route('client.payments.response')
      ->method('POST');  !!}

{!! Former::hidden('gateway_response')->id('gateway_response') !!}
{!! Former::hidden('store_card')->id('store_card') !!}
{!! Former::hidden('payment_hash')->value($payment_hash) !!}
{!! Former::hidden('company_gateway_id')->value($payment_method_id) !!}
{!! Former::hidden('payment_method_id')->value($gateway->getCompanyGatewayId()) !!}
{!! Former::close() !!}


@if($token)
<div class="py-md-5 ninja stripe">

  <div class="form-group">
    <button id="card-button" class="btn btn-primary pull-right" data-secret="{{ $intent->client_secret }}">
          {{ ctrans('texts.pay_now') }} - {{ $token->meta->brand }} - {{ $token ->meta->last4}}
    </button>
  </div>
</div>

@else
<div class="py-md-5 ninja stripe">
    <div class="form-group">
        <input class="form-control" id="cardholder-name" type="text" placeholder="{{ ctrans('texts.name') }}">
    </div>
        <!-- placeholder for Elements -->

    <div class="form-group">
        <div id="card-element" class="form-control"></div>
    </div>

    <div class="form-check form-check-inline mr-1">
    <input class="form-check-input" id="token_billing_checkbox" type="checkbox">
    <label class="form-check-label" for="token_billing_checkbox">{{ ctrans('texts.token_billing_checkbox') }}</label>
    </div>


    <div id="card-errors" role="alert"></div>

    <div class="form-group">
        <button id="card-button" class="btn btn-primary pull-right" data-secret="{{ $intent->client_secret }}">
          {{ ctrans('texts.pay_now') }} 
        </button>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script src="https://js.stripe.com/v3/"></script>

<script type="text/javascript">
    var stripe = Stripe('{{ $gateway->getPublishableKey() }}');

    var elements = stripe.elements();

    var cardholderName = document.getElementById('cardholder-name');
    var cardButton = document.getElementById('card-button');
    var clientSecret = cardButton.dataset.secret;

@if($token)
    cardButton.addEventListener('click', function(ev) {
      stripe.handleCardPayment(
        clientSecret, {
          payment_method: '{{$token->token}}',
        }
      ).then(function(result) {
        if (result.error) {

            $("#card-errors").empty();
            $("#card-errors").append("<b>" + result.error.message + "</b>");
            $("#card-button").removeAttr("disabled");
            $("#store_card").val(0);

        } else {
          // The setup has succeeded. Display a success message.
          console.log(result);
          postResult(result);
        }
      });
    });
@else

    var cardElement = elements.create('card');
    cardElement.mount('#card-element');

    cardButton.addEventListener('click', function(ev) {
      stripe.handleCardPayment(
        clientSecret, cardElement, {
          payment_method_data: {
            billing_details: {name: cardholderName.value}
          }
        }
      ).then(function(result) {
        if (result.error) {

            $("#card-errors").empty();
            $("#card-errors").append("<b>" + result.error.message + "</b>");
            $("#card-button").removeAttr("disabled");

        } else {
          // The setup has succeeded. Display a success message.
          console.log(result);
          postResult(result);
        }
      });
    });

    $("#card-button").attr("disabled", true);

    $('#cardholder-name').on('input',function(e){
      if($("#cardholder-name").val().length >=1)
        $("#card-button").removeAttr("disabled");
      else
        $("#card-button").attr("disabled", true);
    });
@endif

    function postResult(result)
    {

        $("#gateway_response").val(JSON.stringify(result.paymentIntent));
        $("#store_card").val($('#token_billing_checkbox').is(":checked"));
        $("#card-button").attr("disabled", true);
        $('#server_response').submit();
    }

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