@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Details', 'card_title' => 'Bank Details'])

@section('gateway_head')
    {{-- @if($gateway->company_gateway->getConfigField('account_id'))
    <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
    <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else --}}
    {{-- <meta name="stripe-publishable-key" content="{{ $gateway->company_gateway->getPublishableKey() }}"> --}}
    {{-- @endif --}}
@endsection

@section('gateway_content')
    @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
    @endif
    @if(Session::has('error'))
        <div class="alert alert-failure mb-4" id="errors">{{ Session::get('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-failure mb-4">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}" method="post" id="server_response">
        @csrf

        {{-- <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}"> --}}
        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">

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
            <input class="input w-full" id="account-holder-name" type="text" name="account_holder_name" placeholder="{{ ctrans('texts.name') }}" required>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.country')])
            <select name="countries" id="country" name="country" class="form-select input w-full" required>
                @foreach($countries as $country)
                    <option value="{{ $country->iso_3166_2 }}" {{$country->iso_3166_2 == 'US' ? "selected" : ""}}>{{ $country->iso_3166_2 }} ({{ $country->name }})</option>
                @endforeach
            </select>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.currency')])
            <select name="currencies" id="currency" name="currency" class="form-select input w-full">
                @foreach($currencies as $currency)
                    <option value="{{ $currency->code }}" {{$currency->code == 'USD' ? "selected" : ""}}>{{ $currency->code }} ({{ $currency->name }})</option>
                @endforeach
            </select>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
            <input class="input w-full" id="routing-number" name="routing_number" type="text" required>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
            <input class="input w-full" id="account-number" name="account_number" type="text" required>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element-single')
            <input type="checkbox" class="form-checkbox mr-1" name="accept_terms" id="accept-terms" required>
            <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->user()->company->present()->name, 'email' => auth('contact')->user()->client->company->settings->email]) }}</label>
        @endcomponent

        <div class="bg-white px-4 py-5 flex justify-end">
            <button type="button"
                onclick="submitACH()"
                class="button button-primary bg-primary {{ $class ?? '' }}">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                <span>{{ $slot ?? ctrans('texts.add_payment_method') }}</span>
            </button>
            <input type="submit" style="display: none" id="form_btn">
        </div>
    </form>

@endsection

@section('gateway_footer')
    <script>
        function submitACH(){
            let button = document.querySelector("#form_btn");
            button.click();
        }
    </script>
@endsection
