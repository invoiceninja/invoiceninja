@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <link
      rel="stylesheet"
      type="text/css"
      href="https://www.paypalobjects.com/webstatic/en_US/developer/docs/css/cardfields.css"
    />

@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

<div id="paypal-button-container" class="paypal-button-container"></div>
    <div class="card_container">
      <form id="card-form">
        <label for="card-number">Card Number</label>
        <div id="card-number" class="card_field"></div>
        <div style="display: flex; flex-direction: row;">
          <div>
            <label for="expiration-date">Expiration Date</label>
            <div id="expiration-date" class="card_field"></div>
          </div>
          <div style="margin-left: 10px;">
            <label for="cvv">CVV</label>
            <div id="cvv" class="card_field"></div>
          </div>
        </div>
        <label for="card-holder-name">Name on Card</label>
        <input
          type="text"
          id="card-holder-name"
          name="card-holder-name"
          autocomplete="off"
          placeholder="card holder name"
        />
        <div>
          <label for="card-billing-address-street">Billing Address</label>
          <input
            type="text"
            id="card-billing-address-street"
            name="card-billing-address-street"
            autocomplete="off"
            placeholder="street address"
          />
        </div>
        <div>
          <label for="card-billing-address-unit">&nbsp;</label>
          <input
            type="text"
            id="card-billing-address-unit"
            name="card-billing-address-unit"
            autocomplete="off"
            placeholder="unit"
          />
        </div>
        <div>
          <input
            type="text"
            id="card-billing-address-city"
            name="card-billing-address-city"
            autocomplete="off"
            placeholder="city"
          />
        </div>
        <div>
          <input
            type="text"
            id="card-billing-address-state"
            name="card-billing-address-state"
            autocomplete="off"
            placeholder="state"
          />
        </div>
        <div>
          <input
            type="text"
            id="card-billing-address-zip"
            name="card-billing-address-zip"
            autocomplete="off"
            placeholder="zip / postal code"
          />
        </div>
        <div>
          <input
            type="text"
            id="card-billing-address-country"
            name="card-billing-address-country"
            autocomplete="off"
            placeholder="country code"
          />
        </div>
        <br /><br />
        <button value="submit" id="submit" class="btn">Pay</button>
      </form>
@endsection

@section('gateway_footer')
@endsection

@push('footer')
<script src="https://www.paypal.com/sdk/js?components=buttons,hosted-fields&intent=capture&client-id={!! $client_id !!}" data-client-token="{!! $token !!}">
</script>

<script>

if (paypal.HostedFields.isEligible()) {
  paypal.HostedFields.render({ 
       
      createOrder: function(data, actions) {
        return "{!! $order_id !!}"  
      },
      styles: {
      ".valid": {
        color: "green",
      },
      ".invalid": {
        color: "red",
      },
    },
    fields: {
      number: {
        selector: "#card-number",
        placeholder: "4111 1111 1111 1111",
      },
      cvv: {
        selector: "#cvv",
        placeholder: "123",
      },
      expirationDate: {
        selector: "#expiration-date",
        placeholder: "MM/YY",
      },
    },
  }).then((cardFields) => {
    document.querySelector("#card-form").addEventListener("submit", (event) => {
      event.preventDefault();
      cardFields
        .submit({
          // Cardholder's first and last name
          cardholderName: document.getElementById("card-holder-name").value,
          // Billing Address
          billingAddress: {
            // Street address, line 1
            streetAddress: document.getElementById(
              "card-billing-address-street"
            ).value,
            // Street address, line 2 (Ex: Unit, Apartment, etc.)
            extendedAddress: document.getElementById(
              "card-billing-address-unit"
            ).value,
            // State
            region: document.getElementById("card-billing-address-state").value,
            // City
            locality: document.getElementById("card-billing-address-city")
              .value,
            // Postal Code
            postalCode: document.getElementById("card-billing-address-zip")
              .value,
            // Country Code
            countryCodeAlpha2: document.getElementById(
              "card-billing-address-country"
            ).value,
          },
        })
        .then(() => {
          fetch(`/api/orders/${orderId}/capture`, {
            method: "post",
          })
            .then((res) => res.json())
            .then((orderData) => {
              // Two cases to handle:
              //   (1) Other non-recoverable errors -> Show a failure message
              //   (2) Successful transaction -> Show confirmation or thank you
              // This example reads a v2/checkout/orders capture response, propagated from the server
              // You could use a different API or structure for your 'orderData'
              const errorDetail =
                Array.isArray(orderData.details) && orderData.details[0];
              if (errorDetail) {
                var msg = "Sorry, your transaction could not be processed.";
                if (errorDetail.description)
                  msg += "\n\n" + errorDetail.description;
                if (orderData.debug_id) msg += " (" + orderData.debug_id + ")";
                return alert(msg); // Show a failure message
              }
              // Show a success message or redirect
              alert("Transaction completed!");
            });
        })
        .catch((err) => {
          alert("Payment could not be captured! " + JSON.stringify(err));
        });
    });
  });
}
else {
    document.querySelector("#card-form").style = "display: none";

    paypal.Buttons({ 
 env: 'sandbox', // sandbox | production

client: {
    sandbox:    "{{ $gateway->company_gateway->getConfigField('clientId') }}",

},       
      createOrder: function(data, actions) {
        return "{!! $order_id !!}"  
      },
      onApprove: function(data, actions) {

        return actions.order.capture().then(function(details) {                                    
          
          console.log(details);
        });           

            // document.getElementById("gateway_response").value =JSON.stringify( data );
            // document.getElementById("server_response").submit();

      }
    }).render('#paypal-button-container');

}

</script>
@endpush