@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Details', 'card_title' => 'Bank Details'])

@section('gateway_head')
    <meta name="lawpay-public-key" content="{{ $gateway->company_gateway->getConfigField('publicKey') }}">
    <script src="https://cdn.affinipay.com/hostedfields/1.0/fieldGen.js"></script>
@endsection

@section('gateway_content')
    @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
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

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}" method="post" id="server_response">
        @csrf

        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="last_4" id="last_4">

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_type')])
            <span class="flex items-center mr-4">
                <input class="form-radio mr-2" type="radio" value="individual" name="account-holder-type" checked>
                <span>{{ __('texts.individual_account') }}</span>
            </span>
            <span class="flex items-center">
                <input class="form-radio mr-2" type="radio" value="company" name="account-holder-type">
                <span>{{ __('texts.company_account') }}</span>
            </span>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
            <input class="input w-full" id="account-holder-name" type="text" name="account_holder_name" placeholder="{{ ctrans('texts.name') }}" required>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
            <div id="lawpay_routing_number" class="input w-full" style="height: 40px; padding: 8px;"></div>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
            <div id="lawpay_account_number" class="input w-full" style="height: 40px; padding: 8px;"></div>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element-single')
            <input type="checkbox" class="form-checkbox mr-1" name="accept_terms" id="accept-terms" required>
            <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->guard('contact')->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
        @endcomponent

        <div class="bg-white px-4 py-5 flex justify-end">
            <button type="button"
                id="authorize-ach"
                onclick="submitACH()"
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
                    { selector: '#lawpay_routing_number', input: { type: 'routing_number', css: { 'font-size': '14px', 'font-family': 'inherit' } } },
                    { selector: '#lawpay_account_number', input: { type: 'bank_account_number', css: { 'font-size': '14px', 'font-family': 'inherit' } } },
                ]
            }, function(state) {
                // State callback for field validation
            });
        });

        function submitACH() {
            var accountHolderName = document.getElementById('account-holder-name').value;
            var accountHolderType = document.querySelector('input[name="account-holder-type"]:checked').value;
            var acceptTerms = document.getElementById('accept-terms');

            if (!accountHolderName.trim()) {
                document.getElementById('lawpay_errors').innerHTML = '<div class="alert alert-failure mb-4"><ul><li>Account holder name is required.</li></ul></div>';
                return;
            }

            if (!acceptTerms.checked) {
                document.getElementById('lawpay_errors').innerHTML = '<div class="alert alert-failure mb-4"><ul><li>You must accept the ACH authorization terms.</li></ul></div>';
                return;
            }

            var button = document.getElementById('authorize-ach');
            button.disabled = true;
            button.querySelector('svg').classList.remove('hidden');
            button.querySelector('span').classList.add('hidden');

            window.AffiniPay.hostedFields.getPaymentToken({
                account_holder_name: accountHolderName,
                account_holder_type: accountHolderType,
                account_type: 'checking',
            }).then(function(result) {
                document.getElementById('payment_token').value = result.id;
                document.getElementById('last_4').value = result.last_four || '';
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
