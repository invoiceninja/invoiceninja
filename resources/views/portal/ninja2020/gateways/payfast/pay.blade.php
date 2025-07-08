@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="contact-email" content="{{ $contact->email }}">
    <meta name="client-postal-code" content="{{ $contact->client->postal_code }}">
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ $payment_endpoint_url }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="merchant_id" value="{{ $merchant_id }}">
        <input type="hidden" name="merchant_key" value="{{ $merchant_key }}">
        <input type="hidden" name="return_url" value="{{ $return_url }}">
        <input type="hidden" name="cancel_url" value="{{ $cancel_url }}">
        <input type="hidden" name="notify_url" value="{{ $notify_url }}">
        <input type="hidden" name="m_payment_id" value="{{ $m_payment_id }}">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="item_name" value="{{ $item_name }}">
        <input type="hidden" name="item_description" value="{{ $item_description}}">
        <input type="hidden" name="passphrase" value="{{ $passphrase }}"> 
        <input type="hidden" name="signature" value="{{ $signature }}">    

        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="token" id="token">

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
      <ul class="list-none space-y-2">
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
             <li class="py-2 hover:bg-gray-100 rounded transition-colors duration-150">
                <label class="flex items-center cursor-pointer px-2">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-2 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            </li>
            @endforeach
        @endisset

        <li class="py-2 hover:bg-gray-100 rounded transition-colors duration-150">
            <label class="flex items-center cursor-pointer px-2">
                <input
                    type="radio"
                    id="toggle-payment-with-credit-card"
                    class="form-radio text-indigo-600 rounded-full cursor-pointer"
                    name="payment-type"
                    checked/>
                <span class="ml-2 cursor-pointer">{{ __('texts.new_card') }}</span>
            </label>
        </li>
    </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @include('portal.ninja2020.gateways.includes.pay_now')

   </form> 
@endsection

@section('gateway_footer')
<script>
    // Add click listeners to all token radio buttons
    Array.from(document.getElementsByClassName('toggle-payment-with-token'))
        .forEach((element) => {
            element.addEventListener('click', (e) => {
                const sourceInput = document.querySelector('input[name=payment-type]');
                if (sourceInput) {
                    sourceInput.value = e.target.dataset.token;
                }
            });
        });

    // Handle the pay now button click
    const payNowButton = document.getElementById('pay-now');
    if (payNowButton) {
        payNowButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            payNowButton.disabled = true;
            payNowButton.querySelector('#pay-now svg').classList.remove('hidden');
            payNowButton.querySelector('#pay-now span').classList.add('hidden');

            const form = document.getElementById('server_response');
            const selectedToken = document.querySelector('input[name="payment-type"]:checked');
            
            if (selectedToken && selectedToken?.dataset?.token) {
                form.action = "{{ route('client.payments.response') }}";
                document.querySelector('input[name=token]').value = selectedToken.value;
            } else {
                const endpointUrl = document.getElementById('payment_endpoint_url');
                if (endpointUrl) {
                    form.action = endpointUrl.value;
                }
            }
            
            form.submit();
        });
    }
    
    // Auto-select the first payment option if it exists
    const first = document.querySelector('input[name="payment-type"]');
    if (first) {
        first.click();
    }
    
</script>
@endsection

