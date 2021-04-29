@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="client-token" content="{{ $client_token ?? '' }}"/>

    <script src="https://js.braintreegateway.com/web/dropin/1.27.0/js/dropin.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.76.2/js/client.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.76.2/js/data-collector.min.js"></script>

    <style>
        [data-braintree-id="toggle"] {
            display: none;
        }
    </style>
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

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ optional($token->meta)->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label>
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="dropin-container"></div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script type="text/javascript">
        let payNow = document.getElementById('pay-now');

        braintree.client.create({
            authorization: document.querySelector('meta[name=client-token]').content
        }, function (err, clientInstance) {
            braintree.dataCollector.create({
                client: clientInstance,
                paypal: true
            }, function (err, dataCollectorInstance) {
                if (err) {
                    return;
                }

                document.querySelector('input[name=client-data]').value = dataCollectorInstance.deviceData;
            });
        });

        braintree.dropin.create({
            authorization: document.querySelector('meta[name=client-token]').content,
            container: '#dropin-container'
        }, (error, dropinInstance) => {
            if (error) console.error(error);

            payNow.addEventListener('click', () => {
                dropinInstance.requestPaymentMethod((error, payload) => {
                    if (error) {
                        return console.error(error);
                    }

                    document.querySelector('input[name=token]').value = payload.nonce;
                    document.querySelector('input[name=gateway_response]').value = JSON.stringify(payload);

                    let tokenBillingCheckbox = document.querySelector(
                        'input[name="token-billing-checkbox"]:checked'
                    );

                    if (tokenBillingCheckbox) {
                        document.querySelector('input[name="store_card"]').value =
                            tokenBillingCheckbox.value;
                    }

                    document.getElementById('server-response').submit();
                });
            });
        });
    </script>
@endsection
