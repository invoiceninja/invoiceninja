<div class="space-y-10">
    @unless(empty($subscription->product_ids))
        @foreach($subscription->service()->products() as $index => $product)
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
                        <p class="block text-sm">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</p>
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
