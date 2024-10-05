@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_head')
    <meta name="client-token" content="{{ $client_token ?? '' }}" />
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
    @endif

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="nonce" hidden />
        
        @isset($payment_hash)
            <input type="hidden" name="payment_hash" value="{{ $payment_hash }}" />
        @endisset

        @isset($authorize_then_redirect)
            <input type="hidden" name="authorize_then_redirect" value="true" />
        @endisset
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_type')])
        <span class="flex items-center mr-4">
            <input class="form-radio mr-2" type="radio" value="checking" name="account-type" checked>
            <span>{{ __('texts.checking') }}</span>
        </span>
        <span class="flex items-center mt-2">
            <input class="form-radio mr-2" type="radio" value="savings" name="account-type">
            <span>{{ __('texts.savings') }}</span>
        </span>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_type')])
        <span class="flex items-center mr-4">
            <input class="form-radio mr-2" type="radio" value="personal" name="ownership-type" checked>
            <span>{{ __('texts.individual_account') }}</span>
        </span>
        <span class="flex items-center mt-2">
            <input class="form-radio mr-2" type="radio" value="business" name="ownership-type">
            <span>{{ __('texts.company_account') }}</span>
        </span>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
        <input class="input w-full" id="account-holder-name" type="text" placeholder="{{ ctrans('texts.name') }}"
               required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
        <input class="input w-full" id="account-number" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
        <input class="input w-full" id="routing-number" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.address1')])
        <input class="input w-full" id="billing-street-address" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.address2')])
        <input class="input w-full" id="billing-extended-address" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.locality')])
        <input class="input w-full" id="billing-locality" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.state')])
        <select class="input w-full" id="billing-region">
            <option disabled selected></option>
            @foreach(\App\DataProviders\USStates::get() as $code => $state)
                <option value="{{ $code }}">{{ $state }} ({{ $code }})</option>
            @endforeach
        </select>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.postal_code')])
        <input class="input w-full" id="billing-postal-code" type="text" required>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-bank-account'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="https://js.braintreegateway.com/web/3.81.0/js/client.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.81.0/js/us-bank-account.min.js"></script>
    
    @vite('resources/js/clients/payment_methods/braintree-ach.js')
@endsection
