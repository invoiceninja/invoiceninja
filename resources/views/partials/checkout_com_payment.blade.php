@if ($checkoutComDebug)
    <script src="https://sandbox.checkout.com/js/v1/checkout.js"></script>
@else
    <script src="https://cdn.checkout.com/js/checkout.js"></script>
@endif

<form method="POST" class="payment-form">
    <script>
        Checkout.render({
            debugMode: {{ $checkoutComDebug ? 'true' : 'false' }},
            publicKey: '{{ $checkoutComKey }}',
            paymentToken: '{{ $checkoutComToken }}',
            customerEmail: '{{ $contact->email }}',
            customerName: '{{ $contact->getFullName() }}',
            value: {{ $invoice->getRequestedAmount() * 100 }},
            currency: '{{ $invoice->getCurrencyCode() }}',
            widgetContainerSelector: '.payment-form',
            widgetColor: '#333',
            themeColor: '#3075dd',
            buttonColor:'#51c470',
            cardCharged: function(event){
                location.href = '{{ URL::to('/complete?token=' . $checkoutComToken) }}';
            }
        });
    </script>
</form>