<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden px-4 py-5 bg-white sm:gap-4 sm:px-6">

    <p class="font-semibold tracking-tight group flex items-center gap-2 text-lg mb-3">
        {{ ctrans('texts.required_fields') }}
    </p>
    
    @if($is_loading)
        <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
        </svg>
    @else
        <form id="required-client-info-form"
            x-on:submit.prevent="$wire.handleSubmit(Object.fromEntries(new FormData(document.getElementById('required-client-info-form'))))"
            class="-ml-4 lg:-ml-5">
            @foreach($fields as $field)
                @component('portal.ninja2020.components.general.card-element', ['title' => $field['label']])
                @if($field['name'] == 'client_country_id' || $field['name'] == 'client_shipping_country_id')
                    <select id="{{ $field['name'] }}" class="input w-full form-select bg-white" name="{{ $field['name'] }}"
                        wire:model="{{ $field['name'] }}">
                        <option value="none"></option>

                        @foreach($countries as $country)
                            <option value="{{ $country->id }}">
                                {{ $country->iso_3166_2 }} ({{ $country->name }})
                            </option>
                        @endforeach
                    </select>
                @else
                    <input class="input w-full" type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}"
                        wire:model="{{ $field['name'] }}">
                @endif

                @if(count($errors) && array_key_exists($field['name'], $errors))
                    <p class="mt-2 text-gray-900 border-red-300 px-2 py-1 bg-gray-100">
                        {{ $errors[$field['name']][0] }}
                    </p>
                @endif
                @endcomponent

            @endforeach

            <div class="bg-white px-4 py-5 flex items-center w-full justify-end">
                <button type="button" 
                        id="copy-billing-button"
                        class="bg-gray-100 hover:bg-gray-200 px-4 py-2 text-sm rounded transition-colors">
                    {{ ctrans('texts.copy_billing') }}
                </button>
            </div>

            <div class="bg-white px-4 py-5 flex items-center w-full justify-end space-x-3">
                <svg wire:loading class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>

                <button wire:loading.attr="disabled" class="button button-primary bg-primary">
                    <span>{{ ctrans('texts.next') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>

@script
<script>
(function() {
    function copyBillingToShipping() {
        const form = document.getElementById('required-client-info-form');
        if (!form) return;
        
        // Pure vanilla JavaScript - read directly from DOM and update DOM
        // Mapping: billing field => shipping field
        const fieldMappings = [
            { from: 'client_address_line_1', to: 'client_shipping_address_line_1' },
            { from: 'client_address_line_2', to: 'client_shipping_address_line_2' },
            { from: 'client_city', to: 'client_shipping_city' },
            { from: 'client_state', to: 'client_shipping_state' },
            { from: 'client_postal_code', to: 'client_shipping_postal_code' },
            { from: 'client_country_id', to: 'client_shipping_country_id' }
        ];
        
        fieldMappings.forEach(function(mapping) {
            var from = mapping.from;
            var to = mapping.to;
            
            // Find the billing input field
            var billingField = form.querySelector('[name="' + from + '"]');
            // Find the shipping input field
            var shippingField = form.querySelector('[name="' + to + '"]');
            
            if (!billingField || !shippingField) return;
            
            // Try multiple methods to get the current value
            var currentValue = '';
            
            // Method 1: Direct .value property
            var directValue = billingField.value || '';
            
            // Method 2: Try getting from Livewire if available (for wire:model fields)
            var livewireValue = null;
            try {
                // Check if Livewire is available and has the property
                if (typeof window.Livewire !== 'undefined') {
                    var component = window.Livewire.find(billingField.closest('[wire\\:id]')?.getAttribute('wire:id'));
                    if (component) {
                        livewireValue = component.get(from);
                    }
                }
            } catch (e) {
                // Livewire not available or error reading
            }
            
            // Method 3: Use FormData to get form values
            var formData = new FormData(form);
            var formDataValue = formData.get(from);
            
            // Choose the best value - prioritize what user sees/types
            // If direct value exists and is not empty, use it
            // Otherwise try Livewire, then FormData
            if (directValue !== '' && directValue !== null && directValue !== undefined) {
                currentValue = directValue;
            } else if (livewireValue !== null && livewireValue !== undefined && livewireValue !== '') {
                currentValue = String(livewireValue);
            } else if (formDataValue !== null && formDataValue !== undefined) {
                currentValue = String(formDataValue);
            } else {
                currentValue = '';
            }
            
            // Directly set the shipping field's DOM .value property
            shippingField.value = currentValue;
            
            // Trigger the appropriate event so Livewire's wire:model can sync
            if (shippingField.tagName === 'SELECT') {
                // For select elements, trigger 'change' event
                var changeEvent = new Event('change', { bubbles: true, cancelable: true });
                shippingField.dispatchEvent(changeEvent);
            } else {
                // For input elements, trigger 'input' event
                var inputEvent = new Event('input', { bubbles: true, cancelable: true });
                shippingField.dispatchEvent(inputEvent);
            }
        });
    }
    
    // Wait for DOM to be ready, then attach event listener
    function attachListener() {
        var button = document.getElementById('copy-billing-button');
        if (button) {
            button.addEventListener('click', copyBillingToShipping);
        } else {
            // Try again after a short delay in case the button hasn't rendered yet
            setTimeout(attachListener, 100);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachListener);
    } else {
        // DOM is already ready
        attachListener();
    }
})();
</script>
@endscript