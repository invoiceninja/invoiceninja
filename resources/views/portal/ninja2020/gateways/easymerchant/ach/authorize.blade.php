@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_content')
<!--     @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
    @endif -->

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}" method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
    <!-- </form> -->

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div class="alert alert-warning mb-4">
        <h2>Adding a bank account here requires verification, which may take several days. In order to use Instant Verification please pay an invoice first, this process will automatically verify your bank account.</h2>
    </div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_type')])
        <span class="flex items-center mr-4">
            <input class="form-radio mr-2" type="radio" value="individual" name="business_account" checked>
            <span>{{ __('texts.individual_account') }}</span>
        </span>
        <span class="flex items-center">
            <input class="form-radio mr-2" type="radio" value="company" name="business_account">
            <span>{{ __('texts.company_account') }}</span>
        </span>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
        <input class="input w-full" name="account_name" id="account-holder-name" type="text" placeholder="{{ ctrans('texts.name') }}" required value="{{ auth()->guard('contact')->user()->client->present()->name() }}">
    @endcomponent


    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
        <input class="input w-full" name="routing_number" id="routing-number" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
        <input class="input w-full" name="account_number" id="account-number" type="text" required>
    @endcomponent
    
    <!-- @component('portal.ninja2020.components.general.card-element', ['title' => 'Save Account'])
        <input class="form-radio mr-2" type="radio" value="1" name="save_account">Yes
        <input class="form-radio mr-2" type="radio" value="0" name="save_account" checked>No
    </span>
    @endcomponent -->

    @component('portal.ninja2020.components.general.card-element-single')
        <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
        <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'save-button', 'type' => 'submit'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
    </form>
@endsection

@section('gateway_footer')

@endsection
