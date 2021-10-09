<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
        <label for="giropay-name">
            <input class="input w-full" id="giropay-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}">
        </label>
        <div id="mandate-acceptance">
            <input type="checkbox" id="giropay-mandate-acceptance" class="input mr-4">
            <label for="giropay-mandate-acceptance">{{ctrans('texts.giropay_law')}}</label>
        </div>
    @endcomponent
</div>
