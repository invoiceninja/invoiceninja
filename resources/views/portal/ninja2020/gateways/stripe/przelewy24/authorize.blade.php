@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'SEPA', 'card_title' => 'SEPA-Lastschrift'])

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
    <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
    <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
    <meta name="stripe-publishable-key" content="{{ $gateway->company_gateway->getPublishableKey() }}">
    @endif

    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    @if(session()->has('sepa_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('sepa_error') }}</p>
        </div>
    @endif

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::SEPA]) }}" method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="is_default" id="is_default">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_type')])
        <span class="flex items-center mr-4">
            <input class="form-radio mr-2" type="radio" value="individual" name="account-holder-type" checked>
            <span>{{ __('texts.individual_account') }}</span>
        </span>
        <span class="flex items-center">
            <input class="form-radio mr-2" type="radio" value="company" name="account-holder-type">
            <span>{{ __('texts.company_account') }}</span>
        </span>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
        <input class="input w-full" id="account-holder-name" type="text" placeholder="{{ ctrans('texts.name') }}" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.country')])
        <select name="countries" id="country" class="form-select input w-full bg-white" required>
            @foreach($countries as $country)
                <option value="{{ $country->iso_3166_2 }}">{{ $country->iso_3166_2 }} ({{ $country->name }})</option>
            @endforeach
        </select>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.currency')])
        <select name="currencies" id="currency" class="form-select input w-full">
            @foreach($currencies as $currency)
                <option value="{{ $currency->code }}">{{ $currency->code }} ({{ $currency->name }})</option>
            @endforeach
        </select>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
        <input class="input w-full" id="routing-number" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
        <input class="input w-full" id="account-number" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
        <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->guard('contact')->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'save-button'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-sepa.js')
@endsection
