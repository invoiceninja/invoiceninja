<div id="stripe--payment-container">
    @unless(isset($show_name) && $show_name == false)
        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.cardholder_name')])
            <input class="input w-full" id="cardholder-name" type="text" placeholder="{{ ctrans('texts.cardholder_name') }}">
        @endcomponent
    @endunless

    @unless(isset($show_card_element) && $show_card_element == false)
        @component('portal.ninja2020.components.general.card-element-single')
            <div id="card-element" class="border p-4 rounded"></div>
        @endcomponent
    @endunless
</div>

@unless(isset($show_save_method) && $show_save_method == false)
    @include('portal.ninja2020.gateways.includes.save_card')
@endunless
