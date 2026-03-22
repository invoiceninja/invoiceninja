<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="store_card">

        <label for="becs-name">
            <input class="input w-full" id="becs-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}" required>
        </label>

        <label for="becs-email">
            <input class="input w-full" id="becs-email-address" type="email" placeholder="{{ ctrans('texts.email') }}" required>
        </label>

        <label>
            <div class="border p-4 rounded mt-2">
                <div id="becs-iban"></div>
            </div>
        </label>

        <div id="mandate-acceptance" class="mt-2">
            <input type="checkbox" id="becs-mandate-acceptance" class="input mr-4">
            <label for="becs-mandate-acceptance">{!! ctrans('texts.becs_mandate') !!}</label>
        </div>
    </form>
    @endcomponent
</div>
