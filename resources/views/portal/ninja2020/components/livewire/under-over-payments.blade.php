<div x-data="{ payableInvoices: @entangle('payableInvoices'), errors: @entangle('errors') }" class="px-4 py-5 bg-white sm:gap-4 sm:px-6">
    
    <dt class="text-sm font-medium leading-5 text-gray-500">
        {{ ctrans('texts.payment_amount') }}
    </dt>
    <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2 flex flex-col">


    <template x-for="(invoice, index) in payableInvoices" :key="index">
            
        <div class="flex items-center">
            <label>
                <span x-text="'{{ ctrans('texts.invoice') }} ' + invoice.number" class="mt-2"></span>
                <span>{{ $currency->code }} ({{ $currency->symbol }})</span>
                <input
                    type="text"
                    class="input mt-0 mr-4 relative"
                    name="payable_invoices[]"
                    x-model="payableInvoices[index].formatted_amount"
                    />
            </label>
        </div>
    </template>
        
    <template x-if="errors.length > 0">
        <div x-text="errors" class="alert alert-failure mb-4"></div>
    </template>

    @if($settings->client_portal_allow_under_payment)
        <span class="mt-1 text-sm text-gray-800">{{ ctrans('texts.minimum_payment') }}: {{ $settings->client_portal_under_payment_minimum }}</span>
    @endif
    </dd>
    
    <div class="bg-white px-4 py-5 flex w-full justify-end">
        <button 
            class="button button-primary bg-primary payment-method flex items-center justify-center relative py-4" 
            wire:click="checkValue(payableInvoices)">
            <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ ctrans('texts.next') }}</span>
        </button>
    </div>
</div>