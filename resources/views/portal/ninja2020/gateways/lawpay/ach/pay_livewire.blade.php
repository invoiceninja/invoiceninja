<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="lawpay-ach-payment">
    <meta name="lawpay-public-key" content="{{ $gateway->company_gateway->getConfigField('publicKey') }}">

    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="token" id="token" />
        <input type="hidden" name="store_card" id="store_card" />
        <input type="submit" style="display: none" id="form_btn">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="last_4" id="last_4">
        <input type="hidden" name="account_holder_name" id="account_holder_name">
    </form>

    <div id="lawpay_errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.bank_transfer') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="list-none">
            @if(count($tokens) > 0)
                @foreach($tokens as $token)
                <li class="py-2 cursor-pointer">
                    <label class="flex items-center cursor-pointer px-2">
                        <input
                            type="radio"
                            data-token="{{ $token->token }}"
                            name="payment-type"
                            class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token"/>
                        <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                    </label>
                </li>
                @endforeach
            @endisset

            <li class="py-2 cursor-pointer">
                <label class="flex items-center cursor-pointer px-2">
                    <input
                        type="radio"
                        id="toggle-payment-with-new-bank-account"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer"
                        name="payment-type"
                        checked/>
                    <span class="ml-1 cursor-pointer">{{ __('texts.new_bank_account') }}</span>
                </label>
            </li>

            <li>
                <div id="lawpay-ach-container">
                    <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
                        style="display: flex!important; justify-content: center!important;">
                        <input class="input w-full" id="account-holder-name" type="text" placeholder="{{ ctrans('texts.account_holder_name') }}" required>
                    </div>
                    <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
                        style="display: flex!important; justify-content: center!important;">
                        <div id="lawpay_routing_number" class="input w-full" style="height: 40px; padding: 8px;"></div>
                    </div>
                    <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
                        style="display: flex!important; justify-content: center!important;">
                        <div id="lawpay_account_number" class="input w-full" style="height: 40px; padding: 8px;"></div>
                    </div>
                </div>
            </li>
        </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
</div>

@assets
    <script src="https://cdn.affinipay.com/hostedfields/1.0/fieldGen.js"></script>
    @vite('resources/js/clients/payments/lawpay-ach-payment.js')
@endassets
