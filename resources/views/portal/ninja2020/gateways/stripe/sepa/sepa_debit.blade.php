<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
        <input class="input w-full" id="cardholder-name" type="text" placeholder="{{ ctrans('texts.name') }}">
    @endcomponent
</div>
