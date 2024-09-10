<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden bg-white sm:gap-4"
    id="rotessa-bank-transfer-authorize">
    @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
    @endif

    <form action="{{ route('client.payment_methods.store', ['method' => $gateway_type_id]) }}" method="post"
        id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" value="{{ $gateway_type_id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">

        @isset($payment_hash)
            <input type="hidden" name="payment_hash" value="{{ $payment_hash }}" />
        @endif

        @isset($authorize_then_redirect)
            <input type="hidden" name="authorize_then_redirect" value="true" />
        @endisset

        <x-rotessa::contact-component :contact="$contact"></x-rotessa::contact-component>

        <x-rotessa::address-component :address="$address"></x-rotessa::address-component>

        <x-rotessa::account-component :account="$account"></x-rotessa::account-component>

        @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-bank-account', 'type' => 'submit'])
        {{ ctrans('texts.add_payment_method') }}
        @endcomponent
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>
</div>
