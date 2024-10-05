<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="stripe-applepay-payment">
    <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}" />
    <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}" />
    <meta name="stripe-country" content="{{ $country->iso_3166_2 }}" />
    <meta name="stripe-currency" content="{{ $currency }}" />
    <meta name="stripe-total-label" content="{{ ctrans('texts.payment_amount') }}" />
    <meta name="stripe-total-amount" content="{{ $stripe_amount }}" />
    <meta name="stripe-client-secret" content="{{ $intent->client_secret }}" />

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    <div id="payment-request-button">
        <!-- A Stripe Element will be inserted here. -->
    </div>
</div>

@assets
<script src="https://js.stripe.com/v3/"></script>
@vite('resources/js/clients/payments/stripe-applepay.js')
@endassets  