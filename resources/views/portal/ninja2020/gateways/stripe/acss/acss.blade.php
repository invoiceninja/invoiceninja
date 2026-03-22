<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="store_card">

        <label for="acss-name">
            <input class="input w-full" id="acss-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}">
        </label>
        <label for="acss-email" >
            <input class="input w-full" id="acss-email-address" type="email" placeholder="{{ ctrans('texts.email') }}">
        </label>
    </form>
    @endcomponent
</div>
