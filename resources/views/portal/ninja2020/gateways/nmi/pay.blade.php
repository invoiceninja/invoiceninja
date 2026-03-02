@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="nmi-tokenization-key" content="{{ $tokenization_key }}">
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="token" id="token" />
        <input type="hidden" name="store_card" id="store_card" />
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}" />
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="last4" id="last4">
        <input type="hidden" name="exp_month" id="exp_month">
        <input type="hidden" name="exp_year" id="exp_year">
        <input type="hidden" name="card_brand" id="card_brand">
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
                    <input type="radio" data-token="{{ $token->hashed_id }}" name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token" />
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
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
        <div id="nmi-card-container">
            <div id="collectjs-ccnumber" class="mb-3"></div>
            <div class="flex gap-4">
                <div id="collectjs-ccexp" class="flex-1"></div>
                <div id="collectjs-cvv" class="flex-1"></div>
            </div>
        </div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script src="https://secure.nmi.com/token/Collect.js"
            data-tokenization-key="{{ $tokenization_key }}"
            data-variant="inline"
            data-field-ccnumber-selector="#collectjs-ccnumber"
            data-field-ccexp-selector="#collectjs-ccexp"
            data-field-cvv-selector="#collectjs-cvv">
    </script>

    <script>
        const button = document.getElementById('pay-now');
        const cardContainer = document.getElementById('nmi-card-container');

        // Handle toggle between saved tokens and new card
        document.querySelectorAll('.toggle-payment-with-token').forEach(function(radio) {
            radio.addEventListener('click', function(e) {
                document.getElementById('token').value = e.target.dataset.token;
                cardContainer.style.display = 'none';
            });
        });

        var newCardToggle = document.getElementById('toggle-payment-with-credit-card');
        if (newCardToggle) {
            newCardToggle.addEventListener('click', function() {
                document.getElementById('token').value = '';
                cardContainer.style.display = 'block';
            });
        }

        document.getElementById('pay-now').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            button.querySelector('svg').classList.remove('hidden');
            button.querySelector('span').classList.add('hidden');
            e.target.parentElement.disabled = true;

            document.getElementById('errors').hidden = true;

            let tokenInput = document.getElementById('token');

            // Check for save card checkbox
            let tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');
            if (tokenBillingCheckbox) {
                document.getElementById('store_card').value = tokenBillingCheckbox.value;
            }

            // If paying with a saved token, submit directly
            if (tokenInput.value) {
                document.getElementById('server_response').submit();
                return;
            }

            // Otherwise, tokenize the card via Collect.js
            CollectJS.startPaymentRequest();
        });

        CollectJS.configure({
            paymentType: 'cc',
            callback: function(response) {
                document.getElementById('payment_token').value = response.token;

                if (response.card) {
                    var last4 = response.card.number ? response.card.number.slice(-4) : '';
                    document.getElementById('last4').value = last4;
                    document.getElementById('card_brand').value = response.card.type || 'CC';
                    if (response.card.exp) {
                        var parts = response.card.exp.split('/');
                        document.getElementById('exp_month').value = parts[0] || '';
                        document.getElementById('exp_year').value = parts[1] || '';
                    }
                }

                document.getElementById('server_response').submit();
            },
            validationCallback: function(field, status, message) {
                if (!status) {
                    let errorsContainer = document.getElementById('errors');
                    errorsContainer.textContent = message;
                    errorsContainer.hidden = false;

                    button.querySelector('svg').classList.add('hidden');
                    button.querySelector('span').classList.remove('hidden');
                    button.disabled = false;
                }
            },
            fieldsAvailableCallback: function() {
                // Fields loaded and ready
            }
        });
    </script>
@endsection
