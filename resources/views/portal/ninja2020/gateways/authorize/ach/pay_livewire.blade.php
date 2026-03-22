<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="authorize-net-ach-payment">

    <meta name="authorize-public-key" content="{{ $public_client_id }}">
    <meta name="authorize-login-id" content="{{ $api_login_id }}">
    <meta name="instant-payment" content="yes" />

    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
        <input type="hidden" name="token" id="token"/>
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.ach') }}
    @endcomponent
    @include('portal.ninja2020.gateways.includes.payment_details')
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
    <ul class="list-none">
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <li class="py-2 cursor-pointer">
                    <label class="mr-4">
                        <input
                            type="radio"
                            data-token="{{ $token->hashed_id }}"
                            name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token"/>
                        <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                    </label>
                </li>
            @endforeach
        @endisset

        <li class="py-2 cursor-pointer">
            <label>
                <input
                    type="radio"
                    id="toggle-payment-with-ach"
                    class="form-radio cursor-pointer"
                    name="payment-type"
                    checked/>
                <span class="ml-1 cursor-pointer">{{ __('texts.add_bank_account') }}</span>
            </label>
        </li>
    </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.authorize.includes.ach_form')
    @include('portal.ninja2020.gateways.includes.pay_now')

</div>

@assets
    @if($gateway->company_gateway->getConfigField('testMode'))
        <script src="https://jstest.authorize.net/v1/Accept.js" charset="utf-8"></script>
    @else
        <script src="https://js.authorize.net/v1/Accept.js" charset="utf-8"></script>
    @endif

    @vite('resources/js/clients/payments/authorize-ach-payment.js')
@endassets
