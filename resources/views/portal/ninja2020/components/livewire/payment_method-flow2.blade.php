<div class="flex flex-col space-y-4 p-4" x-data="{ isLoading: true }">
     
    <div x-show="isLoading" class="flex items-center justify-center min-h-screen">
        <svg class="animate-spin h-10 w-10 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
    
    @foreach($methods as $index => $method)

            <button 
                class="button button-primary bg-primary payment-method flex items-center justify-center relative py-4" 
                @click="$wire.dispatch('payment-method-selected', { company_gateway_id: {{ $method['company_gateway_id'] }}, gateway_type_id: {{ $method['gateway_type_id'] }}, amount: {{ $amount }} })">
                <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ $method['label'] }}</span>
            </button>

    @endforeach
    

    @script
    <script>

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