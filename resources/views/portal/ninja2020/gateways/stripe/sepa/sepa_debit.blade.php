<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
    
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <label for="sepa-name">
            <input class="input w-full" id="sepa-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}">
        </label>
        <label for="sepa-email" >
            <input class="input w-full" id="sepa-email-address" type="email" placeholder="{{ ctrans('texts.email') }}">
        </label>
        <label>
            <div class="border p-4 rounded">
                <div id="sepa-iban"></div>
            </div>
        </label>
        <div id="mandate-acceptance">
            <input type="checkbox" id="sepa-mandate-acceptance" class="input mr-4">
            <label for="sepa-mandate-acceptance">{{ctrans('texts.sepa_mandat', ['company' => auth('contact')->user()->company->present()->name()])}}</label>
        </div>
    </form>
    @endcomponent
</div>
