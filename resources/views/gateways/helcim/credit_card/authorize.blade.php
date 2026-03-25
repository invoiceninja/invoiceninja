@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    {{-- SECURITY: NO API token is exposed to the frontend --}}
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="is_default" id="is_default" value="0">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

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

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.default_payment_method')])
        <input type="checkbox" id="proxy_is_default" class="form-checkbox mr-1">
        <label for="proxy_is_default" class="cursor-pointer">{{ ctrans('texts.set_as_default') }}</label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-card'])
@endsection

@section('gateway_footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const authorizeButton = document.getElementById('authorize-card');
            const serverResponseForm = document.getElementById('server_response');
            const isDefaultCheckbox = document.getElementById('proxy_is_default');
            const isDefaultInput = document.getElementById('is_default');

            // Handle default checkbox
            if (isDefaultCheckbox) {
                isDefaultCheckbox.addEventListener('change', (e) => {
                    isDefaultInput.value = e.target.checked ? '1' : '0';
                });
            }

            // Handle form submission
            if (authorizeButton) {
                authorizeButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // Validate card inputs
                    const cardNumber = document.getElementById('card_number').value;
                    const cardExpiry = document.getElementById('card_expiry').value;
                    const cardCvv = document.getElementById('card_cvv').value;
                    const cardholderName = document.getElementById('cardholder_name').value;
                    
                    if (!cardNumber || !cardExpiry || !cardCvv || !cardholderName) {
                        alert('Please fill in all card details');
                        return;
                    }

                    // Add card fields to form (they will be processed server-side)
                    serverResponseForm.appendChild(createHiddenInput('card_number', cardNumber));
                    serverResponseForm.appendChild(createHiddenInput('card_expiry', cardExpiry));
                    serverResponseForm.appendChild(createHiddenInput('card_cvv', cardCvv));
                    serverResponseForm.appendChild(createHiddenInput('cardholder_name', cardholderName));
                    
                    authorizeButton.disabled = true;
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