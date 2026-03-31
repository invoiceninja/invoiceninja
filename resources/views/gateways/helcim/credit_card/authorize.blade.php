@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    {{-- PCI COMPLIANCE: HelcimPay.js handles card data securely in an iframe --}}
    <script type="text/javascript" src="https://secure.helcim.app/helcim-pay/services/start.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="is_default" id="is_default" value="0">
        <input type="hidden" name="transaction_data" id="transaction_data" value="">
        <input type="hidden" name="transaction_hash" id="transaction_hash" value="">
        <input type="hidden" name="secret_token" id="secret_token" value="{{ $secret_token }}">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.card_details')])
        <p class="text-sm text-gray-600 mb-4">
            {{ ctrans('texts.click_to_add_card') }}
        </p>
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
            const checkoutToken = '{{ $checkout_token }}';
            const secretToken = '{{ $secret_token }}';

            // Handle default checkbox
            if (isDefaultCheckbox) {
                isDefaultCheckbox.addEventListener('change', (e) => {
                    isDefaultInput.value = e.target.checked ? '1' : '0';
                });
            }

            // PCI COMPLIANCE: Listen for HelcimPay.js verification events
            window.addEventListener('message', (event) => {
                const helcimPayJsIdentifierKey = 'helcim-pay-js-' + checkoutToken;
                
                if (event.data.eventName === helcimPayJsIdentifierKey) {
                    if (event.data.eventStatus === 'ABORTED') {
                        console.error('Card verification failed!', event.data.eventMessage);
                        authorizeButton.disabled = false;
                        
                        const errorsDiv = document.getElementById('errors');
                        errorsDiv.textContent = 'Card verification failed: ' + (event.data.eventMessage || 'Unknown error');
                        errorsDiv.hidden = false;
                    }
                    
                    if (event.data.eventStatus === 'SUCCESS') {
                        console.log('Card verification success!', event.data.eventMessage);
                        
                        // Extract transaction data and hash
                        const transactionData = event.data.eventMessage.data;
                        const transactionHash = event.data.eventMessage.hash;
                        
                        // Store in form
                        document.getElementById('transaction_data').value = JSON.stringify(transactionData);
                        document.getElementById('transaction_hash').value = transactionHash;
                        
                        // Remove the iframe
                        removeHelcimPayIframe();
                        
                        // Submit the form
                        serverResponseForm.submit();
                    }
                    
                    if (event.data.eventStatus === 'HIDE') {
                        console.log('Modal closed.');
                        authorizeButton.disabled = false;
                    }
                }
            });

            // Handle authorize button click
            if (authorizeButton) {
                authorizeButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // PCI COMPLIANCE: Open HelcimPay.js modal for card verification
                    authorizeButton.disabled = true;
                    appendHelcimPayIframe(checkoutToken);
                });
            }

            // Function to remove HelcimPay.js iframe
            function removeHelcimPayIframe() {
                const frame = document.getElementById('helcimPayIframe');
                if (frame instanceof HTMLIFrameElement) {
                    frame.remove();
                }
            }
        });
    </script>
@endsection
