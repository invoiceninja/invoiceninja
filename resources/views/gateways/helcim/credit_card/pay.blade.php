@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    {{-- PCI COMPLIANCE: HelcimPay.js handles card data securely in an iframe --}}
    <meta name="instant-payment" content="yes" />
    <script type="text/javascript" src="https://secure.helcim.app/helcim-pay/services/start.js"></script>
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
        <input type="hidden" name="transaction_data" id="transaction_data" value="">
        <input type="hidden" name="transaction_hash" id="transaction_hash" value="">
        <input type="hidden" name="secret_token" id="secret_token" value="{{ $secret_token }}">
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

    <div id="save-card-container">
        @include('portal.ninja2020.gateways.includes.save_card')
    </div>

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const payNowButton = document.getElementById('pay-now');
            const saveCardContainer = document.getElementById('save-card-container');
            const useTokenInput = document.getElementById('use_token');
            const tokenInput = document.getElementById('token');
            const storeCardInput = document.getElementById('store_card');
            const serverResponseForm = document.getElementById('server_response');
            const checkoutToken = '{{ $checkout_token }}';
            const secretToken = '{{ $secret_token }}';

            // Handle token selection (saved cards)
            document.querySelectorAll('.toggle-payment-with-token').forEach(element => {
                element.addEventListener('click', (e) => {
                    useTokenInput.value = 'true';
                    tokenInput.value = e.target.dataset.token;
                    saveCardContainer.style.display = 'none';
                });
            });

            // Handle new card selection
            const newCardToggle = document.getElementById('toggle-payment-with-credit-card');
            if (newCardToggle) {
                newCardToggle.addEventListener('click', () => {
                    useTokenInput.value = 'false';
                    tokenInput.value = '';
                    saveCardContainer.style.display = 'block';
                });
            }

            // Handle save card checkbox
            const saveCardCheckbox = document.querySelector('input[name="token-billing-checkbox"]');
            if (saveCardCheckbox) {
                saveCardCheckbox.addEventListener('change', (e) => {
                    storeCardInput.value = e.target.checked ? 'true' : 'false';
                });
            }

            // PCI COMPLIANCE: Listen for HelcimPay.js payment events
            window.addEventListener('message', (event) => {
                const helcimPayJsIdentifierKey = 'helcim-pay-js-' + checkoutToken;
                
                if (event.data.eventName === helcimPayJsIdentifierKey) {
                    if (event.data.eventStatus === 'ABORTED') {
                        console.error('Transaction failed!', event.data.eventMessage);
                        payNowButton.disabled = false;
                        
                        const errorsDiv = document.getElementById('errors');
                        errorsDiv.textContent = 'Payment failed: ' + (event.data.eventMessage || 'Unknown error');
                        errorsDiv.hidden = false;
                    }
                    
                    if (event.data.eventStatus === 'SUCCESS') {
                        console.log('Transaction success!', event.data.eventMessage);
                        
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
                        payNowButton.disabled = false;
                    }
                }
            });

            // Handle pay now button click
            if (payNowButton) {
                payNowButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    const usingToken = useTokenInput.value === 'true';
                    
                    if (usingToken) {
                        // Pay with saved card token
                        payNowButton.disabled = true;
                        serverResponseForm.submit();
                    } else {
                        // PCI COMPLIANCE: Open HelcimPay.js modal for new card
                        payNowButton.disabled = true;
                        appendHelcimPayIframe(checkoutToken);
                    }
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
