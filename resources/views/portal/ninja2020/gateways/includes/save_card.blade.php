@unless(isset($show_save) && $show_save == false)
    <div class="{{ ($gateway->token_billing == 'optin' || $gateway->token_billing == 'optout') ? 'sm:grid' : 'hidden' }} px-4 py-5 sm:grid-cols-3 sm:gap-4 sm:px-6">
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
