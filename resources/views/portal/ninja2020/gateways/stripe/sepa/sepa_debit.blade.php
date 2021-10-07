<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
        <label for="sepa-name">
            <input class="input mr-4" id="sepa-name" type="text" placeholder="{{ ctrans('texts.name') }}">
        </label>
        <label for="sepa-email">
            <input class="input mr-4" id="sepa-email-address" type="email" placeholder="{{ ctrans('texts.email') }}">
        </label>
        <div class="border p-4 rounded StripeElement StripeElement--complete">
            <div id="sepa-iban">
                <!-- A Stripe Element will be inserted here. -->
            </div>
        </div>
        <div id="mandate-acceptance">
            <input type="checkbox" id="sepa-mandate-acceptance">
            <label for="sepa-mandate-acceptance">{{ctrans('texts.sepa_mandat')}}</label>
        </div>

    @endcomponent
</div>
