<style type="text/css">
    
</style>

<div class="grid grid-cols-12">
    <div class="col-span-8 bg-gray-50 flex flex-col max-h-100px items-center h-screen">
        <div class="w-full p-4 md:max-w-3xl">
            <div class="w-full mb-4">
                <img class="object-scale-down" style="max-height: 100px;"src="{{ $subscription->company->present()->logo }}" alt="{{ $subscription->company->present()->name }}">
                <h1 id="billing-page-company-logo" class="text-3xl font-bold tracking-wide mt-6">
                {{ $subscription->name }}
                </h1>
            </div>
            @if(!empty($subscription->recurring_product_ids))
                <p
                    class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-md text-xs font-medium">
                    {{ ctrans('texts.recurring_purchases') }}
                </p>
              <ul role="list" class="divide-y divide-gray-200 bg-white">
                @foreach($subscription->service()->recurring_products() as $product)
                <li>
                  <a href="#" class="block hover:bg-gray-50">
                    <div class="px-4 py-4 sm:px-6">
                      <div class="flex items-center justify-between">
                        <div class="ml-2 flex flex-shrink-0">
                          <p class="inline-flex rounded-md bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800"></p>
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
                <p class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-md text-xs font-medium">
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
                              <p class="inline-flex rounded-md bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800"></p>
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

        <div class="w-full px-4 pb-0 md:max-w-3xl">
            <h2 class="text-2xl font-normal text-left">Optional products</h2>
        </div>

        <div class="w-full p-4 md:max-w-3xl">
            @if(!empty($subscription->recurring_product_ids))
                @foreach($subscription->service()->recurring_products() as $product)
                <div class="flex items-center justify-between mb-4 bg-white rounded-md px-6 py-4 shadow-sm border">
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
                    <div class="flex items-center justify-between mb-4 bg-white rounded-md px-6 py-4 shadow-sm border">
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


    <div class="col-span-4 bg-blue-500 flex flex-col item-center p-2">
        <div class="w-full p-4">
            <div id="summary" class="px-4 text-white">
                <h1 class="font-semibold text-2xl border-b-2 border-gray-200 border-opacity-50 pb-2 text-white">{{ ctrans('texts.order') }}</h1>

                @foreach($subscription->service()->recurring_products() as $product)
                <div class="flex justify-between mt-1 mb-1">
                  <span class="font-light text-sm uppercase">{!! nl2br(substr($product->notes, 0, 50)) !!}</span>
                  <span class="font-semibold text-sm">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</span>
                </div>
                @endforeach

                @foreach($subscription->service()->products() as $product)
                <div class="flex justify-between mt-1 mb-1">
                  <span class="font-light text-sm uppercase">{!! nl2br(substr($product->notes, 0, 50)) !!}</span>
                  <span class="font-semibold text-sm">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</span>
                </div>
                @endforeach


                @if(!empty($subscription->promo_code) && !$subscription->trial_enabled)
                <form wire:submit.prevent="handleCoupon" class="">
                @csrf
                    <div class="mt-2">
                      <label for="email" class="block text-sm font-medium text-white">{{ ctrans('texts.promo_code') }}</label>
                      <div class="mt-1 flex rounded-md shadow-sm">
                        <div class="relative flex flex-grow items-stretch focus-within:z-10">
                          <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                          </div>
                          <input type="text" wire:model.defer="coupon" class="block w-full rounded-none rounded-l-md border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="">
                        </div>
                        <button type="button" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                          
                          <span>{{ ctrans('texts.apply') }}</span>
                        </button>
                      </div>
                    </div>
                </form>
                @endif

                <div class="border-t-2 border-gray-200 border-opacity-50 mt-8">
                  <div class="flex font-semibold justify-between py-6 text-sm uppercase">
                    <span>{{ ctrans('texts.total') }}</span>
                    <span>{{ \App\Utils\Number::formatMoney($price, $subscription->company) }}</span>
                  </div>
                  <button class="bg-white font-semibold hover:bg-gray-600 py-3 text-sm text-blue-500 uppercase w-full">Checkout</button>
                </div>
            </div>
        </div>
    </div>


</div>

