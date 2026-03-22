<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="stripe-ach-payment">
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif

    <meta name="client_secret" content="{{ $client_secret }}">
    <meta name="viewport" content="width=device-width, minimum-scale=1" />

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="source" value="">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="customer" value="{{ $customer->id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="client_secret" value="{{ $client_secret }}">
        <input type="hidden" name="gateway_response" id="gateway_response" value="">
        <input type="hidden" name="bank_account_response" id="bank_account_response" value="">
    </form>

    @if(count($tokens) > 0)
        @include('portal.ninja2020.gateways.includes.payment_details')

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            <ul class="list-none hover:list-disc">
                @foreach($tokens as $token)
                    <li class="py-1 hover:text-blue hover:bg-blue-600">
                        <label class="mr-4">
                            <input type="radio" data-token="{{ $token->hashed_id }}" name="payment-type"
                                class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token" />
                            <span class="ml-1 cursor-pointer">{{ ctrans('texts.bank_transfer') }}
                                (*{{ $token->meta->last4 }})</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif
        @endcomponent

        @include('portal.ninja2020.gateways.includes.pay_now')

    @else

        @component('portal.ninja2020.components.general.card-element-single')
        <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
        <label for="accept-terms"
            class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->guard('contact')->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
        <input class="input w-full" id="account-holder-name-field" type="text" placeholder="{{ ctrans('texts.name') }}"
            value="{{ $gateway->client->present()->first_name() }} {{ $gateway->client->present()->last_name() }}" required>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.email')])
        <input class="input w-full" id="email-field" type="text" placeholder="{{ ctrans('texts.email') }}"
            value="{{ $gateway->client->present()->email() }}" required>
        @endcomponent

        <div class="px-4 py-5 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                Connect a bank account
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                <button type="button" class="button button-primary bg-primary" id="new-bank" type="button">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span>{{ $slot ?? ctrans('texts.new_bank_account') }}</span>
                </button>
            </dd>
        </div>
    @endif
</div>

@assets
<script src="https://js.stripe.com/v3/"></script>
@vite('resources/js/clients/payments/stripe-ach-pay.js')
@endassets