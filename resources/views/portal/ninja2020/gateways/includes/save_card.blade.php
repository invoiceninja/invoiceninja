@php

    $gateway_instance = $gateway instanceof \App\Models\CompanyGateway ? $gateway : $gateway->company_gateway;
    $token_billing = true;
    $token_billing_string = 'true';
    $checked_on = '';
    $checked_off = 'checked';

    if($gateway_instance->token_billing == 'off'){
        $token_billing = false;
        $token_billing_string = 'false';
    }

    if($gateway_instance->token_billing == 'always'){
        $token_billing = false;
        $token_billing_string = 'true';
    }

    if($gateway_instance->token_billing == 'optout'){
        $checked_on = 'checked';
        $checked_off = '';
    }
    
    if (isset($pre_payment) && $pre_payment == '1' && isset($is_recurring) && $is_recurring == '1') {
        $token_billing_string = 'true';
    }

@endphp

@if($token_billing)
    <div class="sm:grid px-4 py-5 sm:grid-cols-3 sm:gap-4 sm:px-6" id="save-card--container">
        <dt class="text-sm leading-5 font-medium text-gray-500">
            {{ ctrans('texts.save_payment_method_details') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <label class="mr-4">
                <input type="radio" class="form-radio cursor-pointer" name="token-billing-checkbox"
                       id="proxy_is_default"
                       value="true" {{ $checked_on }}/>
                <span class="ml-1 cursor-pointer">{{ ctrans('texts.yes') }}</span>
            </label>
            <label>
                <input type="radio" class="form-radio cursor-pointer" name="token-billing-checkbox"
                       id="proxy_is_default"
                       value="false" {{ $checked_off }} />
                <span class="ml-1 cursor-pointer">{{ ctrans('texts.no') }}</span>
            </label>
        </dd>
    </div>
@else
    <div id="save-card--container" class="hidden" style="display: none !important;">
        <input type="radio" class="form-radio cursor-pointer hidden" style="display: none !important;"
               name="token-billing-checkbox"
               id="proxy_is_default"
               value="{{ $token_billing_string }}" checked hidden disabled/>
    </div>
@endif
