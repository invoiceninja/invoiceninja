@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Two checkout', 'card_title' => 'Credit card'])
@section('gateway_head')
    <meta name="twocheckout-merchant-code" content="{{ $gateway->company_gateway->getConfigField('merchantCode') }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="raw_value" value="{{ $raw_value }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="card-element">
            <!-- A TCO IFRAME will be inserted here. -->
        </div>
    @endcomponent



    @include('portal.ninja2020.gateways.includes.pay_now')

@endsection

@section('gateway_footer')
    <script type="text/javascript" src="https://2pay-js.2checkout.com/v1/2pay.js"></script>
    <script>
        const merchantCode =
            document.querySelector('meta[name="twocheckout-merchant-code"]')?.content ?? '';

        window.addEventListener('load', function() {
            // Initialize the JS Payments SDK client.
            let jsPaymentClient = new TwoPayClient(merchantCode);

            // Create the component that will hold the card fields.
            let component = jsPaymentClient.components.create('card');

            // Mount the card fields component in the desired HTML tag. This is where the iframe will be located.
            component.mount('#card-element');

            // Handle form submission.
            document.getElementById('pay-now').addEventListener('click', (event) => {
                event.preventDefault();

                // Extract the Name field value
                const billingDetails = {
                    name: 'client name ',
                };

                // Call the generate method using the component as the first parameter
                // and the billing details as the second one
                jsPaymentClient.tokens.generate(component, billingDetails).then((response) => {
                    document.querySelector('input[name="token"]').value =
                        response.token;
                    document.getElementById('server-response').submit()
                }).catch((error) => {
                    console.error(error);
                });
            });
        });
    </script>
@endsection

