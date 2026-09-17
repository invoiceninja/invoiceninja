@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'PromptPay', 'card_title' => 'PromptPay'])

@php
    $promptpay_email = $client->present()->email() ?? ($client->contacts()->first()->email ?? '');
@endphp

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif

    <meta name="stripe-client-secret" content="{{ $client_secret }}">
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <label class="block mt-2">
            <span class="text-sm">{{ ctrans('texts.email_address') }}</span>
            <input class="input w-full" type="email" id="promptpay-email" name="email" value="{{ $promptpay_email }}" placeholder="{{ ctrans('texts.email_address') }}" required>
        </label>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.promptpay') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.promptpay')])
        <p>{{ ctrans('texts.promptpay_instructions') }}</p>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-promptpay.js')
@endsection
