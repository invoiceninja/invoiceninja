<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
     style="display: flex!important; justify-content: center!important;" id="authorize--credit-card-container">
     <div class="card-js" id="my-card" data-capture-name="true">
        <input class="input w-full" id="cardholder_name" name="card_holders_name"
            placeholder="{{ ctrans('texts.name')}}">
        <input type="text" class="input w-full" id="number" placeholder="0000 0000 0000 0000" name="card_number" />
        <div class="flex items-center gap-2">
        <input type="text" class="input w-1/2" id="date" placeholder="MM/YY">
        <input type="text" class="input w-1/2" id="cvv" placeholder="000">
        </div>

        <input type="hidden" class="expiry-month" name="expiry-month" id="expiration_month" autocomplete="cc-exp-month" x-autocompletetype="cc-exp-month">
        <input type="hidden" class="expiry-year" name="expiry-year" id="expiration_year" autocomplete="cc-exp-year" x-autocompletetype="cc-exp-year">
    </div>

    <div id="errors"></div>
</div>
