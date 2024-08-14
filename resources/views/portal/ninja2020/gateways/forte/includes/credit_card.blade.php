<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
     style="display: flex!important; justify-content: center!important;" id="forte--credit-card-container">
    <div class="card-js" id="my-card" data-capture-name="true">
        <input class="name" id="cardholder_name" name="card_holders_name" placeholder="{{ ctrans('texts.name')}}">
        <input class="card-number my-custom-class" id="card_number">
        <input type="hidden" name="expiry_month" id="expiration_month">
        <input type="hidden" name="expiry_year" id="expiration_year">
        <input class="cvc" name="cvc" id="cvv">
    </div>

    <div id="errors"></div>
</div>
