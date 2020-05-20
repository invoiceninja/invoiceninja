@extends('portal.ninja2020.layout.app')
<form class="payment-form" method="POST" action="https://merchant.com/successUrl">
  <script>
    window.CKOConfig = {
      publicKey: 'pk_test_70f73945-07c0-4ec3-8f10-35250c747542',
      customerEmail: 'user@email.com',
      value: {{ $amount }},
      currency: 'USD',
      paymentMode: 'cards',
      cardFormMode: 'cardTokenisation',
      cardTokenised: function(event) {
        console.log(event.data.cardToken);
      }
    };
  </script>
  <script async src="https://cdn.checkout.com/sandbox/js/checkout.js"></script>
</form>
