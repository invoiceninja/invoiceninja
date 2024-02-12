<div class="space-y-10">
    @unless(empty($subscription->optional_recurring_product_ids))
        @foreach($subscription->service()->optional_recurring_products() as $index => $product)
        <div>
            <div class="flex items-start justify-between space-x-4">
                <div class="flex flex-start">
                    @if(filter_var($product->product_image, FILTER_VALIDATE_URL))
                    <div
                        class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2"
                    >
                        <img
                            src="{{ $product->product_image }}"
                            alt=""
                            class="h-full w-full object-cover object-center border rounded-md"
                        />
                    </div>
                    @endif

                    <div class="flex flex-col">
                        <h2 class="text-lg font-medium">{{ $product->product_key }}</h2>
                        <p class="block text-sm">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }} / <span class="lowercase">{{ App\Models\RecurringInvoice::frequencyForKey($subscription->frequency_id) }}</span></p>
                    </div>
                </div>

                <div class="flex flex-col-reverse space-y-3">
                    <div class="flex">
                        @if(is_numeric($product->max_quantity))
                            @if($subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                <p class="text-sm font-light text-red-500 text-right mr-2 mt-2">{{ ctrans('texts.out_of_stock') }}</p>
                            @else
                                <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                            @endif
        
                            <select class="rounded-md border-gray-300 shadow-sm sm:text-sm" @if($subscription->use_inventory_management && $product->in_stock_quantity == 0) disabled @endif>
                                <option value="0" selected="selected">0</option>
                                @for ($i = 1; $i <= ($subscription->use_inventory_management ? min($product->in_stock_quantity, max(100,$product->max_quantity)) : max(100,$product->max_quantity)); $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        @endif
                    </div>
                </div>
            </div>

            <article class="prose my-3 text-sm">
                {!! $product->markdownNotes() !!}
            </article>
        </div>
        @endforeach 
    @endunless
</div>
