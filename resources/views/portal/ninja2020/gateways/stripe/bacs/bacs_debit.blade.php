<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="store_card">

        <label for="bacs-name">
            <input class="input w-full" id="bacs-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}">
        </label>
        <label for="bacs-email" >
            <input class="input w-full" id="bacs-email-address" type="email" placeholder="{{ ctrans('texts.email') }}">
        </label>
        <label>
            <div class="border p-4 rounded">
                <div id="bacs-iban"></div>
            </div>
        </label>
        <div id="mandate-acceptance">
            <input type="checkbox" id="bacs-mandate-acceptance" class="input mr-4">
            <label for="bacs-mandate-acceptance">{{ctrans('texts.bacs_mandat')}}</label>
        </div>
    </form>
    @endcomponent
</div>
