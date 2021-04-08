<div class="grid grid-cols-12 gap-8 mt-8">
    <div class="col-span-12 md:col-span-5 md:col-start-4 px-4 py-5">
        <!-- Total price -->
        <div class="relative mt-8">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>

            <div class="relative flex justify-center text-sm leading-5">
                <h1 class="text-2xl font-bold tracking-wide bg-gray-100 px-6 py-0">
                    {{ ctrans('texts.total') }}: {{ \App\Utils\Number::formatMoney($total, $subscription->company) }}
                    {{--                    <small class="ml-1 line-through text-gray-500">{{ \App\Utils\Number::formatMoney($subscription->price, $subscription->company) }}</small>--}}
                </h1>
            </div>
        </div>

        <!-- Payment methods -->
        <div class="mt-8 flex flex-col items-center">
            <small class="block mb-4">Select a payment method:</small>
            <div>
                @foreach($this->methods as $method)
                    <button
                        {{--                        wire:click="handleMethodSelectingEvent('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}')"--}}
                        class="px-3 py-2 border bg-white rounded mr-4 hover:border-blue-600">
                        {{ $method['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</div>
