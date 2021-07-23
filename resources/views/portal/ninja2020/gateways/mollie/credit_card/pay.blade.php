@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' =>
ctrans('texts.credit_card')])

@section('gateway_head')
    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>
    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
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
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="flex flex-col">
            <label for="card-number">
                <span class="text-xs text-gray-900 uppercase">{{ ctrans('texts.card_number') }}</span>
                <div class="input w-full" type="text" id="card-number"></div>
                <div class="text-xs text-red-500 mt-1 block" id="card-number-error"></div>
            </label>

            <label for="card-holder" class="block mt-2">
                <span class="text-xs text-gray-900 uppercase">{{ ctrans('texts.name') }}</span>
                <div class="input w-full" type="text" id="card-holder"></div>
                <div class="text-xs text-red-500 mt-1 block" id="card-holder-error"></div>
            </label>

            <div class="grid grid-cols-12 gap-4 mt-2">
                <label for="expiry-date" class="col-span-4">
                    <span class="text-xs text-gray-900 uppercase">{{ ctrans('texts.expiry_date') }}</span>
                    <div class="input w-full" type="text" id="expiry-date"></div>
                    <div class="text-xs text-red-500 mt-1 block" id="expiry-date-error"></div>
                </label>

                <label for="cvv" class="col-span-8">
                    <span class="text-xs text-gray-900 uppercase">{{ ctrans('texts.cvv') }}</span>
                    <div class="input w-full border" type="text" id="cvv"></div>
                    <div class="text-xs text-red-500 mt-1 block" id="cvv-error"></div>
                </label>
            </div>
        </div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script src="https://js.mollie.com/v1/mollie.js"></script>

    <script>
        class _Mollie {
            constructor() {
                this.mollie = Mollie('pfl_sFDNzkEdyw', {
                    testmode: true,
                    locale: 'en_US',
                });
            }

            createCardHolderInput() {
                let cardHolder = this.mollie.createComponent("cardHolder");
                cardHolder.mount("#card-holder");

                let cardHolderError = document.getElementById("card-holder-error");

                cardHolder.addEventListener("change", function(event) {
                    if (event.error && event.touched) {
                        cardHolderError.textContent = event.error;
                    } else {
                        cardHolderError.textContent = "";
                    }
                });

                return this;
            }

            createCardNumberInput() {
                let cardNumber = this.mollie.createComponent("cardNumber");
                cardNumber.mount("#card-number");

                let cardNumberError = document.getElementById("card-number-error");

                cardNumber.addEventListener("change", function(event) {
                    if (event.error && event.touched) {
                        cardNumberError.textContent = event.error;
                    } else {
                        cardNumberError.textContent = "";
                    }
                });

                return this;
            }

            createExpiryDateInput() {
                let expiryDate = this.mollie.createComponent("expiryDate");
                expiryDate.mount("#expiry-date");

                let expiryDateError = document.getElementById("expiry-date-error");

                expiryDate.addEventListener("change", function(event) {
                    if (event.error && event.touched) {
                        expiryDateError.textContent = event.error;
                    } else {
                        expiryDateError.textContent = "";
                    }
                });

                return this;
            }

            createCvvInput() {
                let verificationCode = this.mollie.createComponent("verificationCode");
                verificationCode.mount("#cvv");

                let verificationCodeError = document.getElementById(
                    "cvv-error"
                );

                verificationCode.addEventListener("change", function(event) {
                    if (event.error && event.touched) {
                        verificationCodeError.textContent = event.error;
                    } else {
                        verificationCodeError.textContent = "";
                    }
                });

                return this;
            }

            handlePayNowButton() {
                document.getElementById('pay-now').disabled = true;

                this.mollie.createToken().then(function(result) {
                    let token = result.token;
                    let error = result.error;

                    if (error) {
                        document.getElementById('pay-now').disabled = false;

                        let errorsContainer = document.getElementById('errors');
                        errorsContainer.innerText = error.message;
                        errorsContainer.hidden = false;

                        return;
                    }

                    document.querySelector('input[name=token]').value = token;
                    document.getElementById('server-response').submit();
                });
            }

            handle() {
                this
                    .createCardHolderInput()
                    .createCardNumberInput()
                    .createExpiryDateInput()
                    .createCvvInput();

                document
                    .getElementById('pay-now')
                    .addEventListener('click', () => this.handlePayNowButton());
            }
        }

        new _Mollie().handle();
    </script>
@endsection
