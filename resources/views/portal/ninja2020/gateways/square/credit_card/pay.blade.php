@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="square-appId" content="{{ $gateway->company_gateway->getConfigField('applicationId') }}">
    <meta name="square-locationId" content="{{ $gateway->company_gateway->getConfigField('locationId') }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
        <input type="hidden" name="sourceId" id="sourceId">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')


    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if (count($tokens) > 0)
            @foreach ($tokens as $token)
                <label class="mr-4">
                    <input type="radio" data-token="{{ $token->token }}" name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token" />
                    <span class="ml-1 cursor-pointer">**** {{ optional($token->meta)->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label>
            <input type="radio" id="toggle-payment-with-credit-card" class="form-radio cursor-pointer" name="payment-type"
                checked />
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="card-container"></div>
        <div id="payment-status-container"></div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    @if ($gateway->company_gateway->getConfigField('testMode'))
        <script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    @else
        <script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>
    @endif

    <script>
        class SquareCreditCard {
            constructor() {
                this.appId = document.querySelector('meta[name=square-appId]').content;
                this.locationId = document.querySelector('meta[name=square-locationId]').content;
            }

            async init() {
                this.payments = Square.payments(this.appId, this.locationId);

                this.card = await this.payments.card();

                await this.card.attach('#card-container');

                let iframeContainer = document.querySelector('.sq-card-iframe-container');

                if (iframeContainer) {
                    iframeContainer.setAttribute('style', '150px !important');
                }

                let toggleWithToken = document.querySelector('.toggle-payment-with-token');

                if (toggleWithToken) {
                    document.getElementById('card-container').classList.add('hidden');
                }
            }

            async completePaymentWithoutToken(e) {
                document.getElementById('errors').hidden = true;
                e.target.parentElement.disabled = true;

                let result = await this.card.tokenize();

                if (result.status === 'OK') {
                    document.getElementById('sourceId').value = result.token;

                    let tokenBillingCheckbox = document.querySelector(
                        'input[name="token-billing-checkbox"]:checked'
                    );

                    if (tokenBillingCheckbox) {
                        document.querySelector('input[name="store_card"]').value =
                            tokenBillingCheckbox.value;
                    }

                    return document.getElementById('server_response').submit();
                }

                document.getElementById('errors').textContent = result.errors[0].message;
                document.getElementById('errors').hidden = false;

                e.target.parentElement.disabled = false;
            }

            async completePaymentUsingToken(e) {
                e.target.parentElement.disabled = true;

                return document.getElementById('server_response').submit();
            }

            async handle() {
                document
                    .getElementById('authorize-card')
                    ?.addEventListener('click', (e) => this.completePaymentWithoutToken(e));

                document
                    .getElementById('pay-now')
                    .addEventListener('click', (e) => {
                        let tokenInput = document.querySelector('input[name=token]');

                        if (tokenInput.value) {
                            return this.completePaymentUsingToken(e);
                        }

                        return this.completePaymentWithoutToken(e);
                    });

                Array
                    .from(document.getElementsByClassName('toggle-payment-with-token'))
                    .forEach((element) => element.addEventListener('click', (element) => {
                        document.getElementById('card-container').classList.add('hidden');
                        document.getElementById('save-card--container').style.display = 'none';
                        document.querySelector('input[name=token]').value = element.target.dataset.token;
                    }));

                document
                    .getElementById('toggle-payment-with-credit-card')
                    .addEventListener('click', async (element) => {
                        await this.init();

                        document.getElementById('card-container').classList.remove('hidden');
                        document.getElementById('save-card--container').style.display = 'grid';
                        document.querySelector('input[name=token]').value = "";
                    });

                let toggleWithToken = document.querySelector('.toggle-payment-with-token');

                if (!toggleWithToken) {
                    document.getElementById('toggle-payment-with-credit-card').click();
                }
            }
        }

        new SquareCreditCard().handle();
    </script>
@endsection
