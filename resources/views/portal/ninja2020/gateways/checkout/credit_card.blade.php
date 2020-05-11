@extends('portal.ninja2020.layout.app')


<script async src="https://cdn.checkout.com/sandbox/js/checkout.js"></script>

<meta name="public-key" content="{{ $gateway->getPublicKey() }}">
<meta name="customer-email" content="{{ $contact->email }}">

@if($contact->client->getCurrencyCode() == 'BHD' || $contact->client->getCurrencyCode() == 'KWD' || $contact->client->getCurrencyCode() == 'OMR')
    <meta name="value" content="{{ $amount * 1000 }}">
@else
    <meta name="value" content="{{ $amount * 100 }}">
@endif

<meta name="currency" content="{{ $contact->client->getCurrencyCode() }}">

@section('body')
Form:
<form class="payment-form" method="POST" action="{{ url('/payment-successful') }}">
<script>
    Checkout.render({
      publicKey: '{{ $gateway->getPublicKey() }}',
      paymentToken: 'pay_tok_SPECIMEN-000',
      customerEmail: '{{ $contact->email }}',
      value: 100,
      currency: 'GBP',
      cardFormMode: 'cardTokenisation',
      cardTokenised: function(event) {
        console.log(event.data.cardToken);
      }
    });
  </script>
</form>
@endsection