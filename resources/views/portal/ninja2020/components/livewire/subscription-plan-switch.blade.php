<div>
<div class="grid grid-cols-12 gap-8 mt-8">
    <div class="col-span-12 md:col-span-5 md:col-start-4 px-4 py-5">
        <!-- Total price -->

        @if($amount > 0)

        <div class="relative mt-8">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>

            <div class="relative flex justify-center text-sm leading-5">
                <span class="font-bold tracking-wide bg-gray-100 px-6 py-0">{{ ctrans('texts.select_payment_method')}}</span>
                <h1 class="text-2xl font-bold tracking-wide bg-gray-100 px-6 py-0">
                    {{ ctrans('texts.total') }}: {{ \App\Utils\Number::formatMoney($amount, $subscription->company) }}
                </h1>
            </div>
        </div>

            <form action="{{ route('client.payments.process', ['hash' => $hash, 'sidebar' => 'hidden']) }}"
                  method="post" id="payment-method-form">
                @csrf

                @if($state['invoice'] instanceof \App\Models\Invoice)
                    <input type="hidden" name="invoices[]" value="{{ $state['invoice']->hashed_id }}">
                    <input type="hidden" name="payable_invoices[0][amount]"
                           value="{{ $state['invoice']->partial > 0 ? \App\Utils\Number::formatValue($state['invoice']->partial, $state['invoice']->client->currency()) : \App\Utils\Number::formatValue($state['invoice']->balance, $state['invoice']->client->currency()) }}">
                    <input type="hidden" name="payable_invoices[0][invoice_id]"
                           value="{{ $state['invoice']->hashed_id }}">
                @endif

                <input type="hidden" name="action" value="payment">
                <input type="hidden" name="company_gateway_id" value="{{ $state['company_gateway_id'] }}"/>
                <input type="hidden" name="payment_method_id" value="{{ $state['payment_method_id'] }}"/>
            </form>

    <!-- Payment methods -->
        <div class="mt-8 flex flex-col items-center">
            <div>

                @if(!$state['payment_initialised'])
                    @foreach($this->methods as $method)
                        <button
                            wire:click="handleMethodSelectingEvent('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}')"
                            class="px-3 py-2 border bg-white rounded mr-4 hover:border-blue-600">
                            {{ $method['label'] }}
                        </button>
                    @endforeach
                @endif

                @if($state['show_loading_bar'])
                    <div class="flex justify-center">
                        <svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg"
                             fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                @endif
            </div>
        </div>
        @elseif($amount <= 0)

            <div class="relative flex justify-center text-sm leading-5">
                <h1 class="text-2xl font-bold tracking-wide bg-gray-100 px-6 py-0">
                    {{ ctrans('texts.total') }}: {{ \App\Utils\Number::formatMoney($amount, $subscription->company) }}
                </h1>
            </div>
            <div class="relative flex justify-center text-sm leading-5 mt-10">

            <button wire:click="handlePaymentNotRequired" class="px-3 py-2 border rounded mr-4 hover:border-blue-600" wire:loading.attr="disabled" @if($hide_button) disabled @endif>
                @if($hide_button) {{ ctrans('texts.loading') }} @else {{ ctrans('texts.click_to_continue') }} @endif
            </button>
            </div>

        @endif
    </div>
</div>

<script defer>
window.addEventListener('redirectRoute', event => {

    window.location.href = event.detail.route;

})
</script>
</div>