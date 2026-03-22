<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="stripe-klarna-payment">
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->company_gateway->getPublishableKey() }}">
    @endif
    <meta name="return-url" content="{{ $return_url }}">
    <meta name="amount" content="{{ $stripe_amount }}">
    <meta name="country" content="{{ $country }}">
    <meta name="customer" content="{{ $customer }}">
    <meta name="email" content="{{ $gateway->client->present()->email() }}">
    <meta name="address-2" content="{{ $gateway->client->address2 }}">
    <meta name="address-1" content="{{ $gateway->client->address1 }}">
    <meta name="city" content="{{ $gateway->client->city }}">
    <meta name="state" content="{{ $gateway->client->state }}">
    <meta name="postal_code" content="{{ $gateway->client->postal_code }}">
    <meta name="pi-client-secret" content="{{ $pi_client_secret }}">
    <meta name="translation-name-without-special-characters"
        content="{{ ctrans('texts.name_without_special_characters') }}">

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
    {{ ctrans('texts.klarna') }} ({{ ctrans('texts.bank_transfer') }})
    @endcomponent
    @include('portal.ninja2020.gateways.stripe.klarna.klarna')
    @include('portal.ninja2020.gateways.includes.pay_now')
</div>

@assets
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-klarna.js')
@endassets
