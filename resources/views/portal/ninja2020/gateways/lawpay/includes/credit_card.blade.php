<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
     style="display: flex!important; justify-content: center!important;" id="lawpay-credit-card-container">
    <div id="my-card" data-capture-name="true">
        <input class="input w-full" id="cardholder_name" name="card_holders_name"
            placeholder="{{ ctrans('texts.name')}}">
        <div id="lawpay_card_number" class="input w-full" style="height: 40px; padding: 8px;"></div>
        <div class="flex items-center gap-2">
            <input type="text" class="input w-1/3" id="lawpay_exp_month" placeholder="MM" maxlength="2">
            <input type="text" class="input w-1/3" id="lawpay_exp_year" placeholder="YYYY" maxlength="4">
            <div id="lawpay_cvv" class="input w-1/3" style="height: 40px; padding: 8px;"></div>
        </div>
    </div>

    <div id="errors"></div>
</div>
