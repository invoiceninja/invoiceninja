<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="stripe-acss-payment">

    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif

    <meta name="return-url" content="{{ $return_url }}">
    <meta name="amount" content="{{ $stripe_amount }}">
    <meta name="country" content="{{ $country }}">
    <meta name="customer" content="{{ $customer }}">
    <meta name="translation-name-required" content="{{ ctrans('texts.missing_account_holder_name') }}">
    <meta name="translation-email-required" content="{{ ctrans('texts.provide_email') }}">

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
    {{ ctrans('texts.acss') }} ({{ ctrans('texts.bank_transfer') }})
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="token" value="">
        <input type="hidden" name="store_card">
    </form>

    <ul class="list-none hover:list-disc mt-5">

        @foreach($tokens as $token)
            <li class="py-2 hover:text-blue hover:bg-blue-600">

                <label class="mr-4">
                    <input type="radio" data-token="{{ $token->hashed_id }}" name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token" />
                    <span class="ml-1 cursor-pointer">{{ $token->meta?->brand }} (*{{ $token->meta?->last4 }})</span>
                </label>
            </li>
        @endforeach
    </ul>
    @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token'])

    @endcomponent

</div>

@assets
<script src="https://js.stripe.com/v3/"></script>
@vite('resources/js/clients/payments/stripe-acss.js')
@endassets