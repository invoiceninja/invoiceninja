@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    {{-- SECURITY: NO API token is exposed to the frontend --}}
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="use_token" id="use_token" value="false">
        <input type="hidden" name="token" id="token" value="">
        <input type="hidden" name="store_card" id="store_card" value="false">
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
        @endif
        
        <label>
            <input type="radio" id="toggle-payment-with-credit-card" class="form-radio cursor-pointer" name="payment-type" checked />
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    <div id="card-form-container">
        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.card_details')])
            <div class="mb-4">
                <label for="cardholder_name" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ ctrans('texts.cardholder_name') }}
                </label>
                <input type="text" id="cardholder_name" name="cardholder_name" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    required>
            </div>

            <div class="mb-4">
                <label for="card_number" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ ctrans('texts.card_number') }}
                </label>
                <input type="text" id="card_number" name="card_number" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="1234 5678 9012 3456"
                    maxlength="19"
                    required>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="card_expiry" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ ctrans('texts.expiry_date') }}
                    </label>
                    <input type="text" id="card_expiry" name="card_expiry" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="MM/YY"
                        maxlength="5"
                        required>
                </div>
                <div>
                    <label for="card_cvv" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ ctrans('texts.cvv') }}
                    </label>
                    <input type="text" id="card_cvv" name="card_cvv" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="123"
                        maxlength="4"
                        required>
                </div>
            </div>
        @endcomponent

        @include('portal.ninja2020.gateways.includes.save_card')
    </div>

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const payNowButton = document.getElementById('pay-now');
            const cardFormContainer = document.getElementById('card-form-container');
            const useTokenInput = document.getElementById('use_token');
            const tokenInput = document.getElementById('token');
            const storeCardInput = document.getElementById('store_card');
            const serverResponseForm = document.getElementById('server_response');

            // Handle token selection
            document.querySelectorAll('.toggle-payment-with-token').forEach(element => {
                element.addEventListener('click', (e) => {
                    useTokenInput.value = 'true';
                    tokenInput.value = e.target.dataset.token;
                    cardFormContainer.style.display = 'none';
                });
            });

            // Handle new card selection
            const newCardToggle = document.getElementById('toggle-payment-with-credit-card');
            if (newCardToggle) {
                newCardToggle.addEventListener('click', () => {
                    useTokenInput.value = 'false';
                    tokenInput.value = '';
                    cardFormContainer.style.display = 'block';
                });
            }

            // Handle save card checkbox
            const saveCardCheckbox = document.querySelector('input[name="token-billing-checkbox"]');
            if (saveCardCheckbox) {
                saveCardCheckbox.addEventListener('change', (e) => {
                    storeCardInput.value = e.target.checked ? 'true' : 'false';
                });
            }

            // Handle form submission
            if (payNowButton) {
                payNowButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    const usingToken = useTokenInput.value === 'true';
                    
                    if (!usingToken) {
                        // Validate card inputs
                        const cardNumber = document.getElementById('card_number').value;
                        const cardExpiry = document.getElementById('card_expiry').value;
                        const cardCvv = document.getElementById('card_cvv').value;
                        const cardholderName = document.getElementById('cardholder_name').value;
                        
                        if (!cardNumber || !cardExpiry || !cardCvv || !cardholderName) {
                            alert('Please fill in all card details');
                            return;
                        }

                        // Add card fields to form
                        serverResponseForm.appendChild(createHiddenInput('card_number', cardNumber));
                        serverResponseForm.appendChild(createHiddenInput('card_expiry', cardExpiry));
                        serverResponseForm.appendChild(createHiddenInput('card_cvv', cardCvv));
                        serverResponseForm.appendChild(createHiddenInput('cardholder_name', cardholderName));
                    }
                    
                    payNowButton.disabled = true;
                    serverResponseForm.submit();
                });
            }

            function createHiddenInput(name, value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                return input;
            }

            // Format card number with spaces
            document.getElementById('card_number').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\s/g, '');
                let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                e.target.value = formattedValue;
            });

            // Format expiry date
            document.getElementById('card_expiry').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.slice(0, 2) + '/' + value.slice(2, 4);
                }
                e.target.value = value;
            });

            // Only allow numbers for CVV
            document.getElementById('card_cvv').addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        });
    </script>
@endsection