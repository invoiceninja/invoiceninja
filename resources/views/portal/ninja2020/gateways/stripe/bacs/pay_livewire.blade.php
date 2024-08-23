<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="stripe-bacs-payment">
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->company_gateway->getPublishableKey() }}">
    @endif
    <meta name="only-authorization" content="">
    <meta name="translation-payment-method-required" content="{{ ctrans('texts.missing_payment_method') }}">

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="token">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="amount" value={{ $amount }}>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.bacs') }} ({{ ctrans('texts.bank_transfer') }})
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if (count($tokens) > 0)
            @foreach ($tokens as $token)
                <label class="mr-4">
                    <input type="radio" data-token="{{ $token->token }}" name="payment-type"
                           class="form-radio cursor-pointer toggle-payment-with-token" />
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endisset

    @endcomponent
    @include('portal.ninja2020.gateways.includes.pay_now')
</div>

@assets
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-bacs.js')
@endassets