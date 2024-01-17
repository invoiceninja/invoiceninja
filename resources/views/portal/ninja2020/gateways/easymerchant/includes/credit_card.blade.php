<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
     style="display: flex!important; justify-content: center!important;" id="authorize--credit-card-container">
    <div class="card-js" id="my-card" data-capture-name="true">
        <input class="name" id="cardholder_name" name="card-holders-name" autocomplete="off" placeholder="{{ ctrans('texts.name')}}" {{ $is_required ? : "" }}>
        <input class="card-number my-custom-class" id="card_number" autocomplete="off" name="card-number" {{ $is_required ? : "" }}>
        <input class="expiry-month" name="expiry-month" autocomplete="off" id="expiration_month" {{ $is_required ? : "" }}>
        <input class="expiry-year" name="expiry-year" autocomplete="off" id="expiration_year" {{ $is_required ? : "" }}>
        <input class="cvc" name="cvc" id="cvv" >
    </div>

    <div id="errors"></div>
</div>
