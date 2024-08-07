@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
    <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
    <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
    <meta name="stripe-publishable-key" content="{{ $gateway->company_gateway->getPublishableKey() }}">
    @endif
@endsection

@section('gateway_content')
    @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
    @endif

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}" method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div class="alert alert-warning mb-4">
        <h2>Adding a bank account here requires verification, which may take several days. In order to use Instant Verification please pay an invoice first, this process will automatically verify your bank account.</h2>
    </div>

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
        <input class="input w-full" id="account-holder-name" type="text" placeholder="{{ ctrans('texts.name') }}" required value="{{ auth()->guard('contact')->user()->client->present()->name() }}">
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.country')])
        <select name="countries" id="country" class="form-select input w-full bg-white">
            <option disabled selected></option>
            @foreach($countries as $country)
                @if($country->iso_3166_2 == 'US')
                <option value="{{ $country->iso_3166_2 }}" selected>{{ $country->iso_3166_2 }} ({{ $country->getName() }})</option>
                @else
                <option value="{{ $country->iso_3166_2 }}">{{ $country->iso_3166_2 }} ({{ $country->getName() }})</option>
                @endif
            @endforeach
        </select>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.currency')])
        <select name="currencies" id="currency" class="form-select input w-full">
            <option disabled selected></option>
            @foreach($currencies as $currency)
                @if($currency->code == 'USD')
                    <option value="{{ $currency->code }}" selected>{{ $currency->code }} ({{ $currency->getName() }})</option>
                @else
                    <option value="{{ $currency->code }}">{{ $currency->code }} ({{ $currency->name }})</option>
                @endif
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
    @vite('resources/js/clients/payments/stripe-ach.js')
@endsection
