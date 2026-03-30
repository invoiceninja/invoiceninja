@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="lawpay-public-key" content="{{ $gateway->company_gateway->getConfigField('publicKey') }}">
    <script src="https://cdn.affinipay.com/hostedfields/1.0/fieldGen.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="card_brand" id="card_brand">
        <input type="hidden" name="exp_month" id="exp_month">
        <input type="hidden" name="exp_year" id="exp_year">
        <input type="hidden" name="last_4" id="last_4">

        @if(!Request::isSecure())
            <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
        @endif

        @if(Session::has('error'))
            <div class="alert alert-failure mb-4" id="errors">{{ Session::get('error') }}</div>
        @endif
        <div id="lawpay_errors"></div>
        @if ($errors->any())
            <div class="alert alert-failure mb-4">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
            {{ ctrans('texts.credit_card') }}
        @endcomponent

        @include('portal.ninja2020.gateways.lawpay.includes.credit_card')

        <div class="bg-white px-4 py-5 flex justify-end">
            <button type="button"
                id="authorize-card"
                onclick="submitCard()"
                class="button button-primary bg-primary {{ $class ?? '' }}">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                <span>{{ $slot ?? ctrans('texts.add_payment_method') }}</span>
            </button>
            <input type="submit" style="display: none" id="form_btn">
        </div>
    </form>
@endsection

@section('gateway_footer')
    <script defer>
        var hostedFieldsInstance = null;

        document.addEventListener('DOMContentLoaded', function() {
            var publicKey = document.querySelector('meta[name="lawpay-public-key"]').content;

            hostedFieldsInstance = window.AffiniPay.hostedFields.initializeFields({
                publicKey: publicKey,
                fields: [
                    { selector: '#lawpay_card_number', input: { type: 'credit_card_number', css: { 'font-size': '14px', 'font-family': 'inherit' } } },
                    { selector: '#lawpay_cvv', input: { type: 'cvv', css: { 'font-size': '14px', 'font-family': 'inherit' } } },
                ]
            }, function(state) {
                // State callback for field validation
            });
        });

        function submitCard() {
            var expMonth = document.getElementById('lawpay_exp_month').value.replace(/[^\d]/g, '');
            var expYear = document.getElementById('lawpay_exp_year').value.replace(/[^\d]/g, '');

            if (!expMonth || !expYear) {
                document.getElementById('lawpay_errors').innerHTML = '<div class="alert alert-failure mb-4"><ul><li>Please enter a valid expiration date.</li></ul></div>';
                return;
            }

            var button = document.getElementById('authorize-card');
            button.disabled = true;
            button.querySelector('svg').classList.remove('hidden');
            button.querySelector('span').classList.add('hidden');

            document.getElementById('exp_month').value = expMonth;
            document.getElementById('exp_year').value = expYear;

            window.AffiniPay.hostedFields.getPaymentToken({
                exp_month: expMonth,
                exp_year: expYear,
                name: document.getElementById('cardholder_name').value,
            }).then(function(result) {
                document.getElementById('payment_token').value = result.id;
                document.getElementById('last_4').value = result.last_four || '';
                document.getElementById('card_brand').value = result.card_type || '';
                document.getElementById('form_btn').click();
            }).catch(function(error) {
                var errors = '<div class="alert alert-failure mb-4"><ul><li>' + (error.message || 'Tokenization failed') + '</li></ul></div>';
                document.getElementById('lawpay_errors').innerHTML = errors;
                button.disabled = false;
                button.querySelector('svg').classList.add('hidden');
                button.querySelector('span').classList.remove('hidden');
            });
        }
    </script>
@endsection
