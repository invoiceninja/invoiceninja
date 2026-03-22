<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
        <label for="bancontact-name">
            <input class="input w-full" id="bancontact-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}">
        </label>
    @endcomponent
</div>
