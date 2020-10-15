@unless(isset($show_name) && $show_name == false)
    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            {{ ctrans('texts.name') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="cardholder-name" type="text" placeholder="{{ ctrans('texts.name') }}">
        </dd>
    </div>
@endunless

@unless(isset($show_card_element) && $show_card_element == false)
    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
        <dt class="text-sm leading-5 font-medium text-gray-500">
            {{ ctrans('texts.credit_card') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <div id="card-element"></div>
        </dd>
    </div>
@endunless

@unless(isset($show_save) && $show_save == false)
    <div class="{{ ($gateway->token_billing == 'optin' || $gateway->token_billing == 'optout') ? 'sm:grid' : 'hidden' }} bg-gray-50 px-4 py-5 sm:grid-cols-3 sm:gap-4 sm:px-6">
        <dt class="text-sm leading-5 font-medium text-gray-500">
            {{ ctrans('texts.token_billing_checkbox') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <label class="mr-4">
                <input type="radio" class="form-radio cursor-pointer" name="token-billing-checkbox" id="proxy_is_default" value="true" {{ ($gateway->token_billing == 'always' || $gateway->token_billing == 'optout') ? 'checked' : '' }} />
                <span class="ml-1 cursor-pointer">{{ ctrans('texts.yes') }}</span>
            </label>
            <label>
                <input type="radio" class="form-radio cursor-pointer" name="token-billing-checkbox" id="proxy_is_default" value="false" {{ ($gateway->token_billing == 'off' || $gateway->token_billing == 'optin') ? 'checked' : '' }} />
                <span class="ml-1 cursor-pointer">{{ ctrans('texts.no') }}</span>
            </label>
        </dd>
    </div>
@endunless
