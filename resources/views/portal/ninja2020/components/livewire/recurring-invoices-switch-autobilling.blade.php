<label class="flex items-center cursor-pointer">
    <input type="checkbox" class="form-checkbox mr-2"
           wire:change="updateAutoBilling" {{ $invoice->auto_bill_enabled ? 'checked' : '' }}>

    <span class="text-sm leading-5 font-medium text-gray-900">
        {{ $invoice->auto_bill_enabled || $invoice->auto_bill === 'optout' ? ctrans('texts.auto_bill_enabled') : ctrans('texts.auto_bill_disabled') }}
    </span>
</label>
