@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="nmi-tokenization-key" content="{{ $tokenization_key }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="token" id="token">
        <input type="hidden" name="last4" id="last4">
        <input type="hidden" name="exp_month" id="exp_month">
        <input type="hidden" name="exp_year" id="exp_year">
        <input type="hidden" name="card_brand" id="card_brand">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="nmi-card-container">
            <div id="collectjs-ccnumber" class="mb-3"></div>
            <div class="flex gap-4">
                <div id="collectjs-ccexp" class="flex-1"></div>
                <div id="collectjs-cvv" class="flex-1"></div>
            </div>
        </div>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now')
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
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

        document.getElementById('pay-now').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            button.querySelector('svg').classList.remove('hidden');
            button.querySelector('span').classList.add('hidden');
            e.target.parentElement.disabled = true;

            document.getElementById('errors').hidden = true;

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
