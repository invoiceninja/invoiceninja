<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="giftup-payment">

    <div class="alert alert-failure mb-4 text-red-600" hidden id="errors"></div> 
    
    
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.giftup') }} (Redeem Giftcard)
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')
 
 

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response" class="space-y-4 px-6">
        @csrf

        <!-- Giftcard Code Field -->
        <div class="flex items-center gap-4">
            <label for="giftcard_code" class="text-sm font-medium text-gray-700 whitespace-nowrap">
                Giftcard Code
            </label>
               <input type="text" name="giftcard_code" id="giftcard_code"
                    placeholder="Enter giftcard code here"
                    required
                    class="flex-1 border border-gray-300 rounded-md shadow-sm px-2 py-1 text-sm" />
        </div>


        <!-- Hidden Inputs -->
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="token">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
    </form>

    @include('portal.ninja2020.gateways.includes.pay_now')
</div>

@script
<script>
    document.getElementById('pay-now').addEventListener('click', function () {
        document.getElementById('server-response').submit();
    });
</script>
@endscript
