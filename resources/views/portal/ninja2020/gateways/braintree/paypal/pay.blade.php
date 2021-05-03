@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.paypal'), 'card_title' => ctrans('texts.paypal')])

@section('gateway_head')
    <meta name="client-token" content="{{ $client_token ?? '' }}"/>

    <script src="https://js.braintreegateway.com/web/3.76.2/js/client.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.76.2/js/paypal-checkout.min.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
        <input type="hidden" name="client-data">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.paypal') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="paypal-button"></div>
    @endcomponent
@endsection

@section('gateway_footer')
    <script>
        braintree.client.create({
            authorization: document.querySelector('meta[name=client-token]').content,
        }).then(function (clientInstance) {
            // Create a PayPal Checkout component.
            return braintree.paypalCheckout.create({
                client: clientInstance
            });
        }).then(function (paypalCheckoutInstance) {
            return paypalCheckoutInstance.loadPayPalSDK({
                vault: true
            }).then(function (paypalCheckoutInstance) {
                return paypal.Buttons({
                    fundingSource: paypal.FUNDING.PAYPAL,

                    createBillingAgreement: function () {
                        return paypalCheckoutInstance.createPayment({
                            flow: 'vault', // Required

                            // The following are optional params
                            billingAgreementDescription: 'Your agreement description',
                            enableShippingAddress: true,
                            shippingAddressEditable: false,
                            shippingAddressOverride: {
                                recipientName: 'Scruff McGruff',
                                line1: '1234 Main St.',
                                line2: 'Unit 1',
                                city: 'Chicago',
                                countryCode: 'US',
                                postalCode: '60652',
                                state: 'IL',
                                phone: '123.456.7890'
                            }
                        });
                    },

                    onApprove: function (data, actions) {
                        return paypalCheckoutInstance.tokenizePayment(data).then(function (payload) {
                            // Submit `payload.nonce` to your server
                        });
                    },

                    onCancel: function (data) {
                        console.log('PayPal payment canceled', JSON.stringify(data, 0, 2));
                    },

                    onError: function (err) {
                        console.error('PayPal error', err);
                    }
                }).render('#paypal-button');
            });
        }).catch(function (err) {
            console.log(err.message);

            let errorsContainer = document.getElementById('errors');

            errorsContainer.innerText = err.message;
            errorsContainer.hidden = false;
        });
    </script>
@endsection
