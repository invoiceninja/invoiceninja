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
              <ul role="list" class="divide-y divide-gray-200 bg-white">
                @foreach($subscription->service()->recurring_products() as $product)
                <li>
                  <a href="#" class="block hover:bg-gray-50">
                    <div class="px-4 py-4 sm:px-6">
                      <div class="flex items-center justify-between">
                        <p class="truncate text-sm font-medium text-gray-600">{!! ctrans('texts.recurring_purchases') !!}</p>
                        <div class="ml-2 flex flex-shrink-0">
                          <p class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800"></p>
                        </div>
                      </div>
                      <div class="mt-2 sm:flex sm:justify-between">
                        <div class="sm:flex">
                          <p class="text-sm font-medium text-gray-900 mt-2">{!! nl2br($product->notes) !!}</p>
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
                <ul role="list" class="divide-y divide-gray-200 bg-white">
                    @foreach($subscription->service()->products() as $product)
                    <li>
                      <a href="#" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                          <div class="flex items-center justify-between">
                            <p class="truncate text-sm font-medium text-gray-600">{!! ctrans('texts.one_time_purchases') !!}</p>
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
              <ul role="list" class="divide-y divide-gray-200 bg-white">
                @foreach($subscription->service()->recurring_products() as $product)
                <li>
                  <a href="#" class="block hover:bg-gray-50">
                    <div class="px-4 py-4 sm:px-6">
                      <div class="flex items-center justify-between">
                        <p class="truncate text-sm font-medium text-gray-600">{!! ctrans('texts.recurring_purchases') !!}</p>
                        <div class="ml-2 flex flex-shrink-0">
                          <p class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800"></p>
                        </div>
                      </div>
                      <div class="mt-2 sm:flex sm:justify-between">
                        <div class="sm:flex">
                          <p class="text-sm font-medium text-gray-900 mt-2">{!! nl2br($product->notes) !!}</p>
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
                <ul role="list" class="divide-y divide-gray-200 bg-white">
                    @foreach($subscription->service()->products() as $product)
                    <li>
                      <a href="#" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                          <div class="flex items-center justify-between">
                            <p class="truncate text-sm font-medium text-gray-600">{!! ctrans('texts.one_time_purchases') !!}</p>
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
    </div>


    <div class="col-span-12 xl:col-span-4 bg-blue-500 flex flex-col item-center">
        <div class="w-full p-4 md:max-w-3xl">


        </div>
    </div>


</div>

