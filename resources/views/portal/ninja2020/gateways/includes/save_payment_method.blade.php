@php
    $token_billing = ! $force_save_card && in_array($token_billing_policy, ['optin', 'optout'], true);
    $token_billing_string = $force_save_card || ($prepayment_recurring ?? false) || in_array($token_billing_policy, ['always', 'optout'], true) ? 'true' : 'false';
    $checked_on = $token_billing_policy === 'optout' ? 'checked' : '';
    $checked_off = $token_billing_policy === 'optout' ? '' : 'checked';
@endphp

@if($token_billing)
    <div class="sm:grid px-4 py-5 sm:grid-cols-3 sm:gap-4 sm:px-6" id="save-payment-method--container">
        <dt class="text-sm leading-5 font-medium text-gray-500">
            {{ ctrans('texts.save_payment_method_details') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <label class="flex items-center cursor-pointer px-2">
                <input type="radio" class="form-radio cursor-pointer" name="token-billing-checkbox"
                       id="proxy_is_default"
                       value="true" {{ $checked_on }}/>
                <span class="ml-1 cursor-pointer">{{ ctrans('texts.yes') }}</span>
            </label>
            <label class="flex items-center cursor-pointer px-2">
                <input type="radio" class="form-radio cursor-pointer" name="token-billing-checkbox"
                       id="proxy_is_default"
                       value="false" {{ $checked_off }} />
                <span class="ml-1 cursor-pointer">{{ ctrans('texts.no') }}</span>
            </label>
        </dd>
    </div>
@else
    <div id="save-payment-method--container" class="hidden" style="display: none !important;">
        <input type="radio" class="form-radio cursor-pointer hidden" style="display: none !important;"
               name="token-billing-checkbox"
               id="proxy_is_default"
               value="{{ $token_billing_string }}" checked hidden disabled/>
    </div>
@endif
