<div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
        {{ ctrans('texts.name') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="cardholder_name" type="text" placeholder="{{ ctrans('texts.name') }}">
    </dd>
</div>
<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.credit_card') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="card_number" type="text" placeholder="{{ ctrans('texts.card_number') }}">
    </dd>
</div>
<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.expiration_month') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="expiration_month" type="text" placeholder="{{ ctrans('texts.expiration_month') }}">
    </dd>
</div>
<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.expiration_year') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="expiration_year" type="text" placeholder="{{ ctrans('texts.expiration_year') }}">
    </dd>
</div>
<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.cvv') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="cvv" type="text" placeholder="{{ ctrans('texts.cvv') }}">
    </dd>
</div>
