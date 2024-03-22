<div class="space-y-10">
    @isset($context['bundle']['one_time_products'])
        @foreach($context['bundle']['one_time_products'] as $key => $entry)

        @php
            $product = $entry['product'];
        @endphp

        <div>
            <div class="flex items-start justify-between space-x-4">
                <div class="flex flex-start">
                    @if(filter_var($product['product_image'], FILTER_VALIDATE_URL))
                    <div
                        class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2"
                    >
                        <img
                            src="{{ $product['product_image'] }}"
                            alt=""
                            class="h-full w-full object-cover object-center border rounded-md"
                        />
                    </div>
                    @endif

                    <div class="flex flex-col">
                        <h2 class="text-lg font-medium">{{ $product['product_key'] }}</h2>
                        <p class="block text-sm">{{ \App\Utils\Number::formatMoney($product['price'], $subscription['company']) }}</p>
                    </div>
                </div>

                <div class="flex flex-col-reverse space-y-3">
                    <div class="flex">
                        @if($subscription->per_seat_enabled)
                            @if($subscription->use_inventory_management && $product['in_stock_quantity'] <= 0)
                                <p class="text-sm font-light text-red-500 text-right mr-2 mt-2">{{ ctrans('texts.out_of_stock') }}</p>
                            @else
                                <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                            @endif

                            <select id="{{ $product['hashed_id'] }}" wire:change="quantity($event.target.id, $event.target.value)" class="rounded-md border-gray-300 shadow-sm sm:text-sm" {{ $subscription->use_inventory_management && $product['in_stock_quantity'] < 1 ? 'disabled' : '' }}>
                                <option {{ $entry['quantity'] == '0' ? 'selected' : '' }} value="0" selected="selected">0</option>
                                @for ($i = 1; $i <= ($subscription->use_inventory_management ? min($product['in_stock_quantity'], min(100,$product['max_quantity'])) : min(100,$product['max_quantity'])); $i++)
                                    <option {{ $entry['quantity'] == $i ? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        @endif
                    </div>
                </div>
            </div>

            <article class="prose my-3 text-sm">
                {!! \App\Models\Product::markdownHelp($product['notes']) !!}
            </article>
        </div>
        @endforeach 
    @endisset
</div>