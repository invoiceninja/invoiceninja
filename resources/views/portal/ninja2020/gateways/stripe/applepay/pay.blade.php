@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Apple Pay', 'card_title' => 'Apple Pay'])

@section('gateway_head')
  <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    <div id="payment-request-button">
      <!-- A Stripe Element will be inserted here. -->
    </div>

@endsection

@push('footer')
    <script src="https://js.stripe.com/v3/"></script>

    <script type="text/javascript">

    @if($gateway->company_gateway->getConfigField('account_id'))
      var stripe = Stripe('{{ config('ninja.ninja_stripe_publishable_key') }}', {
        apiVersion: "2018-05-21",
        stripeAccount: '{{ $gateway->company_gateway->getConfigField('account_id') }}',
      });
    @else
      var stripe = Stripe('{{ $gateway->getPublishableKey() }}', {
        apiVersion: "2018-05-21",
      });
    @endif

    var paymentRequest = stripe.paymentRequest({
      country: '{{ $country->iso_3166_2 }}',
      currency: '{{ $currency }}',
      total: {
        label: '{{ ctrans('texts.payment_amount') }}',
        amount: {{ $stripe_amount }},
      },
      requestPayerName: true,
      requestPayerEmail: true,
    });

    var elements = stripe.elements();
    var prButton = elements.create('paymentRequestButton', {
      paymentRequest: paymentRequest,
    });

    // Check the availability of the Payment Request API first.
    paymentRequest.canMakePayment().then(function(result) {
      if (result) {
        prButton.mount('#payment-request-button');
      } else {
        document.getElementById('payment-request-button').style.display = 'none';
      }
    });

    paymentRequest.on('paymentmethod', function(ev) {
      // Confirm the PaymentIntent without handling potential next actions (yet).
      stripe.confirmCardPayment(
        '{{ $intent->client_secret }}',
        {payment_method: ev.paymentMethod.id},
        {handleActions: false}
      ).then(function(confirmResult) {
        if (confirmResult.error) {
          // Report to the browser that the payment failed, prompting it to
          // re-show the payment interface, or show an error message and close
          // the payment interface.
          ev.complete('fail');
        } else {
          // Report to the browser that the confirmation was successful, prompting
          // it to close the browser payment method collection interface.
          ev.complete('success');
          // Check if the PaymentIntent requires any actions and if so let Stripe.js
          // handle the flow. If using an API version older than "2019-02-11"
          // instead check for: `paymentIntent.status === "requires_source_action"`.
          if (confirmResult.paymentIntent.status === "requires_action") {
            // Let Stripe.js handle the rest of the payment flow.
            stripe.confirmCardPayment(clientSecret).then(function(result) {
              if (result.error) {
                // The payment failed -- ask your customer for a new payment method.
                handleFailure(result.error)
              } else {
                // The payment has succeeded.
                handleSuccess(result);
              }
            });
          } else {
            // The payment has succeeded.
          }
        }
      });
    });

    handleSuccess(result) {
      document.querySelector(
          'input[name="gateway_response"]'
      ).value = JSON.stringify(result.paymentIntent);

      document.getElementById('server-response').submit();
    }

    handleFailure(message) {
        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = message;
        errors.hidden = false;

    }

    </script>
@endpush