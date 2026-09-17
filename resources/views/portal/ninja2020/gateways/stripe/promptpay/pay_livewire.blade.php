<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="stripe-promptpay-payment">
    @if($stripe_account_id)
        <meta name="stripe-account-id" content="{{ $stripe_account_id }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $company_gateway->getPublishableKey() }}">
    @endif

    <meta name="stripe-client-secret" content="{{ $client_secret }}">
    <meta name="viewport" content="width=device-width, minimum-scale=1" />

    @php
        $promptpay_email = $client->present()->email() ?? ($client->contacts()->first()->email ?? '');
    @endphp

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <div class="px-4 py-2">
            <label class="block">
                <span class="text-sm">{{ ctrans('texts.email_address') }}</span>
                <input class="input w-full" type="email" id="promptpay-email" name="email" value="{{ $promptpay_email }}" placeholder="{{ ctrans('texts.email_address') }}" required>
            </label>
        </div>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.promptpay') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.promptpay')])
        <p class="px-2">{{ ctrans('texts.promptpay_instructions') }}</p>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')

    @assets
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-promptpay.js')
    @endassets
</div>
