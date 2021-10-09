<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
    <label for="p24-name">
        <input class="input w-full" id="p24-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}">
    </label>
    <label for="p24-email" >
        <input class="input w-full" id="p24-email-address" type="email" placeholder="{{ ctrans('texts.email') }}">
    </label>
    <label for="p24-bank-element"></label>
    <div class="border p-4 rounded"><div id="p24-bank-element"></div>
    </div>
    @endcomponent
</div>
