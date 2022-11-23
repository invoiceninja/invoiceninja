<style type="text/css">
    
</style>

<div class="grid grid-cols-12">
    <div class="col-span-12 xl:col-span-8 bg-gray-50 flex flex-col max-h-100px items-center">
        <div class="w-full p-8 md:max-w-3xl">
            <img class="object-scale-down" style="max-height: 100px;"src="{{ $subscription->company->present()->logo }}" alt="{{ $subscription->company->present()->name }}">

            <h1 id="billing-page-company-logo" class="text-3xl font-bold tracking-wide mt-6">
            {{ $subscription->name }}
            </h1>
        </div>
        <div class="w-full p-4 md:max-w-3xl">
            @if(!empty($subscription->recurring_product_ids))
                <p
                    class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-full text-xs font-medium">
                    {{ ctrans('texts.recurring_purchases') }}
                </p>
              <ul role="list" class="divide-y divide-gray-200 bg-white">
                @foreach($subscription->service()->recurring_products() as $product)
                <li>
                  <a href="#" class="block hover:bg-gray-50">
                    <div class="px-4 py-4 sm:px-6">
                      <div class="flex items-center justify-between">
                        <div class="ml-2 flex flex-shrink-0">
                          <p class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800"></p>
                        </div>
                      </div>
                      <div class="mt-0 sm:flex sm:justify-between">
                        <div class="sm:flex">
                          <p class="text-sm font-medium text-gray-900 mt-0">{!! nl2br($product->notes) !!}</p>
                        </div>
                        <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                            <span data-ref="price">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }} / {{ App\Models\RecurringInvoice::frequencyForKey($subscription->frequency_id) }}</span>
                        </div>
                      </div>
                    </div>
                  </a>
                </li>
                @endforeach
            </ul>
            @endif
        </div>
        <div class="w-full p-4 md:max-w-3xl">

            @if(!empty($subscription->product_ids))
                <p
                    class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-full text-xs font-medium">
                    {{ ctrans('texts.one_time_purchases') }}
                </p>
                <ul role="list" class="divide-y divide-gray-200 bg-white">
                    @foreach($subscription->service()->products() as $product)
                    <li>
                      <a href="#" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                          <div class="flex items-center justify-between">
                            <p class="truncate text-sm font-medium text-gray-600"></p>
                            <div class="ml-2 flex flex-shrink-0">
                              <p class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800"></p>
                            </div>
                          </div>
                          <div class="mt-2 sm:flex sm:justify-between">
                            <div class="sm:flex">
                              <p class="text-sm font-medium text-gray-900 mt-2">{!! nl2br($product->notes) !!}</p>
                            </div>
                            <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                <span data-ref="price">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</span>
                            </div>
                          </div>
                        </div>
                      </a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="relative mt-8">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>

            <div class="relative flex justify-center text-sm leading-5">
                <h1 class="text-2xl font-bold tracking-wide bg-gray-50 px-6 py-0">Optional products</h1>
            </div>
        </div>

        <div class="w-full p-4 md:max-w-3xl">
            @if(!empty($subscription->recurring_product_ids))
                @foreach($subscription->service()->recurring_products() as $product)
                <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                    <div class="text-sm">{!! nl2br($product->notes) !!}</div>
                    <div data-ref="price-and-quantity-container">
                        <span data-ref="price">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }} / {{ App\Models\RecurringInvoice::frequencyForKey($subscription->frequency_id) }}</span>
                        {{--                                <span data-ref="quantity" class="text-sm">(1x)</span>--}}
                    </div>
                </div>
                @endforeach
            @endif
        </div>
        <div class="w-full p-4 md:max-w-3xl">

            @if(!empty($subscription->product_ids))
                @foreach($subscription->service()->products() as $product)
                    <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                        <div class="text-sm">{!! nl2br($product->notes) !!}</div>
                        <div data-ref="price-and-quantity-container">
                            <span
                                data-ref="price">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</span>
                            {{--                                <span data-ref="quantity" class="text-sm">(1x)</span>--}}
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>


    <div class="col-span-12 xl:col-span-4 bg-blue-500 flex flex-col item-center ">
        <div class="w-full p-4 md:max-w-3xl">
            <div id="summary" class="w-1/4 px-8 text-white">
                <h1 class="font-semibold text-2xl border-b pb-8 text-white">Order Summary</h1>
                <div class="flex justify-between mt-10 mb-5">
                  <span class="font-semibold text-sm uppercase">Items 3</span>
                  <span class="font-semibold text-sm">590$</span>
                </div>
                <div>
                  <label class="font-medium inline-block mb-3 text-sm uppercase">Shipping</label>
                  <select class="block p-2 text-white w-full text-sm">
                    <option>Standard shipping - $10.00</option>
                  </select>
                </div>
                <div class="py-10">
                  <label for="promo" class="font-semibold inline-block mb-3 text-sm uppercase">Promo Code</label>
                  <input type="text" id="promo" placeholder="Enter your code" class="p-2 text-sm w-full">
                </div>
                <button class="bg-white hover:bg-gray-600 px-5 py-2 text-sm text-blue-500 uppercase">Apply</button>
                <div class="border-t mt-8">
                  <div class="flex font-semibold justify-between py-6 text-sm uppercase">
                    <span>Total cost</span>
                    <span>$600</span>
                  </div>
                  <button class="bg-white font-semibold hover:bg-gray-600 py-3 text-sm text-blue-500 uppercase w-full">Checkout</button>
                </div>
            </div>
        </div>
    </div>


</div>

