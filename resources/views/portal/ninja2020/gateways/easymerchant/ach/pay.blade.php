@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_head')

        <meta name="client_secret" content="{{ $client_secret }}">
        <meta name="viewport" content="width=device-width, minimum-scale=1" />

@endsection

@section('gateway_content')
    <!-- <div class="alert alert-failure mb-4" hidden id="errors"></div> -->

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="source" value="">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="customer" value="{{ $customer }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="client_secret" value="{{ $client_secret }}">
        <input type="hidden" name="gateway_response" id="gateway_response" value="">
        <input type="hidden" name="bank_account_response" id="bank_account_response" value="">
    

        @include('portal.ninja2020.gateways.includes.payment_details')

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            @if(count($tokens) > 0)
            <ul class="list-none hover:list-disc">
                @foreach($tokens as $token)
                    <li class="py-1 hover:text-blue hover:bg-blue-600">
                        <label class="mr-4">
                            <input
                                onClick="toggleAccount()"
                                type="radio"
                                data-token="{{ $token->hashed_id }}"
                                name="payment-type"
                                value="{{ $token->token }}"
                                class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token"/>
                            <span class="ml-1 cursor-pointer">{{ ctrans('texts.bank_transfer') }} (*{{ $token->meta->last4 }})</span>
                        </label>
                    </li>
                @endforeach
                <li class="py-1 hover:text-blue hover:bg-blue-600">
                    <input class="form-radio mr-2" type="radio" value="new_account" onclick="toggleAccount()" name="payment-type" checked>
                <span>{{ "New account" }}</span>
                </li>
            </ul>
            @else
            <ul class="list-none hover:list-disc">
                 <li class="py-1 hover:text-blue hover:bg-blue-600">
                    <input class="form-radio mr-2" type="radio" value="new_account" onclick="toggleAccount()" name="payment-type" checked>
                <span>{{ "New account" }}</span>
                </li>
            </ul>
            @endif
        @endcomponent
        <div id="toggle-account">
            @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_type')])
                <span class="flex items-center mr-4 new_account">
                    <input class="form-radio mr-2" type="radio" value="individual" name="business_account" checked>
                    <span>{{ __('texts.individual_account') }}</span>
                </span>
                <span class="flex items-center new_account">
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

            @component('portal.ninja2020.components.general.card-element', ['title' => 'Save Account'])
                <!-- <span class="flex items-center new_account"> -->
                    <input class="form-radio mr-2" type="radio" value="1" name="save_account">Yes
                    <input class="form-radio mr-2" type="radio" value="0" name="save_account" checked>No
                <!-- <span>{{ "Save Account" }}</span> -->
            </span>
            @endcomponent

        </div>
            @component('portal.ninja2020.components.general.card-element-single')
                <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
                <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
            @endcomponent

        <div class="bg-white px-4 py-5 flex justify-end">
            <button 
                id="{{ $id ?? 'pay-now' }}"
                class="button button-primary bg-primary {{ $class ?? '' }}">
                <span>{{ ctrans('texts.pay_now') }}</span>
            </button>
        </div>
        </form>
    @endsection

@push('footer')
<!-- <script src="https://js.stripe.com/v3/"></script> -->

@endpush
<script type="text/javascript">

    function toggleAccount() {
      var switch_account = document.getElementById('toggle-account');
      var routing_number = document.getElementById('routing-number');
      var account_number = document.getElementById('account-number');
      var account = document.querySelector('input[name="payment-type"]:checked').value;
      if (account === "new_account") {
        switch_account.style.display = 'block';
        routing_number.setAttribute('required', '');
        account_number.setAttribute('required', '');
      } else {
        switch_account.style.display = 'none';
        routing_number.removeAttribute('required');
        account_number.removeAttribute('required');
      }
    }
</script>