<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="lawpay-credit-card-payment">
    <meta name="lawpay-public-key" content="{{ $gateway->company_gateway->getConfigField('publicKey') }}">

    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="card_brand" id="card_brand">
        <input type="hidden" name="exp_month" id="exp_month">
        <input type="hidden" name="exp_year" id="exp_year">
        <input type="hidden" name="last_4" id="last_4">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="token" id="token" />
        <input type="hidden" name="store_card" id="store_card" />
        <input type="submit" style="display: none" id="form_btn">
    </form>

    <div id="lawpay_errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => 'Pay with Credit Card'])
        @include('portal.ninja2020.gateways.lawpay.includes.credit_card')
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
</div>

@assets
    <script src="https://cdn.affinipay.com/hostedfields/1.0/fieldGen.js"></script>
    @vite('resources/js/clients/payments/lawpay-credit-card-payment.js')
@endassets
