<div class="flex flex-col p-4 rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden px-4 py-5 bg-white sm:gap-4 sm:px-6"
    x-data="{ isLoading: @entangle('isLoading') }">

    <p class="font-semibold tracking-tight group flex items-center gap-2 text-lg">{{ ctrans('texts.payment_methods') }}
    </p>

    <svg id="spinner" wire:loading class="animate-spin h-5 w-5 text-primary"
        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
        </path>
    </svg>

    @unless($isLoading)
        <div class="my-3 flex flex-col space-y-3">
            @foreach($methods as $index => $method)
                <button wire:loading.remove
                    class="flex px-4 py-3 border rounded-lg lg:-mb-1 hover:shadow-sm transition duration-300"
                    wire:click="handleSelect('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}', '{{ $amount }}')"
                    <span>{{ $method['label'] }}</span>
                </button>
            @endforeach
        </div>
    @endunless 

    @script
    <script>
        Livewire.on('loadingCompleted', () => {
            isLoading = false;
        });

        Livewire.on('singlePaymentMethodFound', (event) => {
            $wire.dispatch('payment-method-selected', { company_gateway_id: event.company_gateway_id, gateway_type_id: event.gateway_type_id, amount: event.amount })
        });

        const buttons = document.querySelectorAll('.payment-method');

        buttons.forEach(button => {
            button.addEventListener('click', (event) => {
                // Hide all buttons except the clicked one
                buttons.forEach(btn => {
                    if (btn !== event.currentTarget) {
                        btn.style.display = 'none';
                    } else {
                        // Disable the clicked button
                        btn.disabled = true;

                        // Show the spinner by removing the 'hidden' class
                        const spinner = btn.querySelector('svg');
                        if (spinner) {
                            spinner.classList.remove('hidden');
                        }

                        const span = btn.querySelector('span');
                        if (span) {
                            span.style.display = 'none';
                        }
                    }
                });
            });
        });
    </script>
    @endscript
</div>