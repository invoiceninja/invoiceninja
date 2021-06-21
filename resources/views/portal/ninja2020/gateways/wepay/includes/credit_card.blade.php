<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
     style="display: flex!important; justify-content: center!important;" id="wepay--credit-card-container">
    <div class="card-js" id="my-card" data-capture-name="true">
        <input class="name" id="cardholder_name" name="card-holders-name" placeholder="{{ ctrans('texts.name')}}" required>
        <input class="card-number my-custom-class" id="card_number" name="card-number" required>
        <input class="expiry-month" name="expiry-month" id="expiration_month" required>
        <input class="expiry-year" name="expiry-year" id="expiration_year" required>
        <input class="cvc" name="cvc" id="cvv" required>
    </div>

    <div id="errors"></div>
</div>
