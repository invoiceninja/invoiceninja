<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
        <label for="giropay-name">
            <input class="input w-full" id="ideal-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}">
        </label>
        <label for="ideal-bank-element"></label>
        <div class="border p-4 rounded">
            <div id="ideal-bank-element"></div>
        </div>
    @endcomponent
</div>
