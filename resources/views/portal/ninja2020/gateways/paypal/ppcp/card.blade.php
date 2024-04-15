@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => 'PayPal'])

@section('gateway_head')

@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" id="gateway_type_id" value="{{ $gateway_type_id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

   <div id="paypal-button-container" class="paypal-button-container"></div>
    <div id="checkout-form">
      <!-- Containers for Card Fields hosted by PayPal -->
      <div id="card-name-field-container"></div>
      <div id="card-number-field-container"></div>
      <div id="card-expiry-field-container"></div>
      <div id="card-cvv-field-container"></div>
      
      <button id="card-field-submit-button" type="button">
        Pay now with Card Fields
      </button>

@endsection

@section('gateway_footer')
@endsection

@push('footer')

<script src="https://www.paypal.com/sdk/js?client-id={!! $client_id !!}&components=card-fields&debug=true"  data-partner-attribution-id="invoiceninja_SP_PPCP"></script>

<script>

    const clientId = "{{ $client_id }}";
    const orderId = "{!! $order_id !!}";

    const cardField = paypal.CardFields({
        // env: 'production',
        // fundingSource: fundingSource,
        client: clientId,
        createOrder: function(data, actions) {
            return orderId;  
        },
        onApprove: function(data, actions) {

            var errorDetail = Array.isArray(data.details) && data.details[0];
                if (errorDetail && ['INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED'].includes(errorDetail.issue)) {
                return actions.restart();
            }

                document.getElementById("gateway_response").value =JSON.stringify( data );
                document.getElementById("server_response").submit();

        },
        onCancel: function() {
            window.location.href = "/client/invoices/";
        },
        onError: function(error) {
            document.getElementById("gateway_response").value = error;
            document.getElementById("server_response").submit();
        },
        onClick: function (){
           // document.getElementById('paypal-button-container').hidden = true;
        }
    
    })
    .render('#paypal-button-container');
    // .render('#paypal-button-container').catch(function(err) {
        
    //   document.getElementById('errors').textContent = err;
    //   document.getElementById('errors').hidden = false;
        
    // });
    
    document.getElementById("server_response").addEventListener('submit', (e) => {
		if (document.getElementById("server_response").classList.contains('is-submitting')) {
			e.preventDefault();
		}
		
		document.getElementById("server_response").classList.add('is-submitting');
	});

// Render each field after checking for eligibility
if (cardField.isEligible()) {
  const nameField = cardField.NameField();
  nameField.render("#card-name-field-container");

  const numberField = cardField.NumberField();
  numberField.render("#card-number-field-container");

  const cvvField = cardField.CVVField();
  cvvField.render("#card-cvv-field-container");

  const expiryField = cardField.ExpiryField();
  expiryField.render("#card-expiry-field-container");

  // Add click listener to submit button and call the submit function on the CardField component
  document
    .getElementById("card-field-submit-button")
    .addEventListener("click", () => {
      cardField.submit({
        // From your billing address fields
        billingAddress: {
          addressLine1: document.getElementById(
            "card-billing-address-line-1",
          ).value,
          addressLine2: document.getElementById(
            "card-billing-address-line-2",
          ).value,
          adminArea1: document.getElementById(
            "card-billing-address-admin-area-line-1",
          ).value,
          adminArea2: document.getElementById(
            "card-billing-address-admin-area-line-2",
          ).value,
          countryCode: document.getElementById(
            "card-billing-address-country-code",
          ).value,
          postalCode: document.getElementById(
            "card-billing-address-postal-code",
          ).value,
        }
      }).then(() => {
        // submit successful
      });
    });
}
else {
    console.log("i must not be eligible?");
}

</script>

@endpush