<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
        <div class="form-group mb-[10px]" id="klarna-name-correction" hidden>
            <input class="input w-full" id="klarna-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}" value="{{ $gateway->client->present()->name()}}">
        </div>

    @endcomponent
</div>
