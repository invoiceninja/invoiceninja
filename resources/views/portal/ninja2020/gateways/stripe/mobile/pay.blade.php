@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.apple_pay'), 'card_title' => ctrans('texts.apple_pay')])

@section('gateway_head')
    <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
@endsection

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.apple_pay') }}
    @endcomponent

    <div id="payment-request-button"></div>

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script src="https://js.stripe.com/v3/"></script>

    <script>
        document.getElementById('pay-now').hidden = true;

        var stripe = Stripe('pk_test_TYooMQauvdEDq54NiTphI7jx', {
            apiVersion: "2020-08-27",
        });

        var paymentRequest = stripe.paymentRequest({
            country: 'US',
            currency: 'usd',
            total: {
                label: 'Demo total',
                amount: 1099,
            },
            requestPayerName: true,
            requestPayerEmail: true,
        });

        var elements = stripe.elements();
        var prButton = elements.create('paymentRequestButton', {
            paymentRequest: paymentRequest,
        });

        paymentRequest.canMakePayment().then(function (result) {
            if (result) {
                console.log('Supported..');
                document.getElementById('pay-now').hidden = false;

                prButton.mount('#payment-request-button');
            } else {
                console.log('Unsupported..');
                document.getElementById('payment-request-button').style.display = 'none';
            }
        });
    </script>
@endsection
