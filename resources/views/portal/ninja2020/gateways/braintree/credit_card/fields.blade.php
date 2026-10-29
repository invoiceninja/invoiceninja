<div id="braintree-hosted-fields" class="grid gap-4" wire:ignore>
    <div>
        <label for="braintree-card-number">{{ ctrans('texts.card_number') }}</label>
        <div id="braintree-card-number" class="input w-full h-12"></div>
    </div>
    <div>
        <label for="braintree-expiration-date">{{ ctrans('texts.expiry_date') }}</label>
        <div id="braintree-expiration-date" class="input w-full h-12"></div>
    </div>
    <div id="braintree-cvv-container">
        <label for="braintree-cvv">{{ ctrans('texts.cvv') }}</label>
        <div id="braintree-cvv" class="input w-full h-12"></div>
    </div>
    <div id="braintree-postal-code-container">
        <label for="braintree-postal-code">{{ ctrans('texts.postal_code') }}</label>
        <div id="braintree-postal-code" class="input w-full h-12"></div>
    </div>
</div>
