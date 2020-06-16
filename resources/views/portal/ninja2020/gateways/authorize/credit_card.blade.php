<div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center flex items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
        {{ ctrans('texts.name') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="cardholder_name" type="text" placeholder="{{ ctrans('texts.name') }}">
    </dd>
</div>
<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.credit_card') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="card_number" type="tel" inputmode="numeric" pattern="[0-9\s]{13,19}" autocomplete="cc-number" maxlength="19" placeholder="xxxx xxxx xxxx xxxx">
        <div class="validation validation-fail" id="card_number_errors" hidden></div>
    </dd>
</div>
<div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.expiration_month') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="expiration_month" type="tel" inputmode="numeric" pattern="[0-9\s]{13,19}" autocomplete="cc-month" maxlength="2">
        <div class="validation validation-fail" id="expiration_month_errors" hidden></div>
    </dd>
</div>
<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.expiration_year') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="expiration_year" type="tel" inputmode="numeric" pattern="[0-9\s]{13,19}" autocomplete="cc-year" maxlength="4">
        <div class="validation validation-fail" id="expiration_year_errors" hidden></div>
    </dd>
</div>
<div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.cvv') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="cvv" type="tel" inputmode="numeric" pattern="[0-9\s]{13,19}" autocomplete="cc-cvv" maxlength="5">
    </dd>
</div>