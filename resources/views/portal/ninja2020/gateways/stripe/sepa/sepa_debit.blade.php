<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
        <label for="sepa-name">
            <input class="input w-full" id="sepa-name" type="text" placeholder="{{ ctrans('texts.name') }}">
        </label>
        <label for="sepa-email">
            <input class="input w-full" id="sepa-email-address" type="email" placeholder="{{ ctrans('texts.email') }}">
        </label>
        <label for="sepa-iban">
            <input class="input w-full" id="sepa-iban" type="text">
        </label>
        <div id="mandate-acceptance">
            {{ctrans('texts.sepa_mandat')}}
        </div>
    @endcomponent
</div>
