@extends('portal.ninja2020.layout.app')

<script src="https://cdn.checkout.com/sandbox/js/checkout.js"></script>
<form class="payment-form" method="POST" action="https://merchant.com/successUrl">
    <script>
        Checkout.render({
            publicKey: 'pk_test_6ff46046-30af-41d9-bf58-929022d2cd14',
            paymentToken: 'pay_tok_SPECIMEN-000',
            customerEmail: 'user@email.com',
            value: 100,
            currency: 'GBP',
            cardFormMode: 'cardTokenisation',
            cardTokenised: function(event) {
                console.log(event.data.cardToken);
            }
        });
    </script>
</form>

<!--
            Checkout.render({
            debugMode: {{ $gateway->getConfigField('testMode') ? 'true' : 'false' }},
            publicKey: '{{ $gateway->getConfigField('publicApiKey') }}',
            paymentToken: '{{ $token }}',
            customerEmail: '{{ $contact->email }}',
            customerName: '{{ $contact->getFullName() }}',
            @if( $invoice->getCurrencyCode() == 'BHD' ||  $invoice->getCurrencyCode() == 'KWD' ||  $invoice->getCurrencyCode() == 'OMR')
            value: {{ $invoice->getRequestedAmount() * 1000 }},
            @else
            value: {{ $invoice->getRequestedAmount() * 100 }},
            @endif
            currency: '{{ $invoice->getCurrencyCode() }}',
            widgetContainerSelector: '.payment-form',
            widgetColor: '#333',
            themeColor: '#3075dd',
            buttonColor:'#51c470',
            cardCharged: function(event){
                location.href = '{{ URL::to('/complete/'. $invitation->invitation_key . '/credit_card?token=' . $transactionToken) }}';
            }
        });

    -->