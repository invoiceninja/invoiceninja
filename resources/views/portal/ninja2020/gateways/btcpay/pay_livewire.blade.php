<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="btcpay-payment">
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
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
