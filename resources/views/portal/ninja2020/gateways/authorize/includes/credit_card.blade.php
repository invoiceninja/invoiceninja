<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
     style="display: flex!important; justify-content: center!important;" id="authorize--credit-card-container">
    <div class="card-js" id="my-card" data-capture-name="true">
        <input class="name" id="cardholder_name" name="card-holders-name" placeholder="{{ ctrans('texts.name')}}" autocomplete="off">
        <input class="card-number my-custom-class" id="card_number" name="card-number" autocomplete="off">
        <input class="expiry-month" name="expiry-month" id="expiration_month" autocomplete="off">
        <input class="expiry-year" name="expiry-year" id="expiration_year" autocomplete="off">
        <input class="cvc" name="cvc" id="cvv" autocomplete="off">
    </div>

    <div id="errors"></div>
</div>
