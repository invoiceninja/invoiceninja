<div class="grid grid-cols-12">
    <div class="col-span-8 bg-gray-50 flex flex-col max-h-100px items-center h-screen">
        <div class="w-full p-4 md:max-w-3xl">
            <div class="w-full mb-4">
                <img class="object-scale-down" style="max-height: 100px;"src="{{ $subscription->company->present()->logo }}" alt="{{ $subscription->company->present()->name }}">
                <h1 id="billing-page-company-logo" class="text-3xl font-bold tracking-wide mt-6">
                {{ $subscription->name }}
                </h1>
            </div>
            <!-- Recurring Plan Products-->
            <ul role="list" class="-my-6 divide-y divide-gray-200">
            @if(!empty($subscription->recurring_product_ids))
                @foreach($subscription->service()->recurring_products() as $index => $product)
                    <li class="flex py-6">
                      @if(false)
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200">
                        <img src="https://tailwindui.com/img/ecommerce-images/shopping-cart-page-04-product-01.jpg" alt="Salmon orange fabric pouch with match zipper, gray zipper pull, and adjustable hip belt." class="h-full w-full object-cover object-center">
                      </div>
                      @endif
                      <div class="ml-0 flex flex-1 flex-col">
                        <div>
                          <div class="flex justify-between text-base font-medium text-gray-900">
                            <h3>
                              {!! nl2br($product->notes) !!}
                            </h3>
                            <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }} / {{ App\Models\RecurringInvoice::frequencyForKey($subscription->frequency_id) }}</p>
                          </div>
                          <p class="mt-1 text-sm text-gray-500"></p>
                        </div>
                        <div class="flex content-end text-sm mt-1">
                            @if($subscription->per_seat_enabled)
                            <p class="text-gray-500 w-full"></p>
                            <div class="flex place-content-end">
                                <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                                <input wire:model="data.{{ $index }}.recurring_qty" type="text" class="w-1/4 rounded-md border-gray-300 shadow-sm sm:text-sm text-center" placeholder="0"/>
                            </div>
                            @endif
                        </div>
                        {{ isset($data[$index]['recurring_qty']) ? $data[$index]['recurring_qty'] : 'merp' }}

                        @if($errors)
                            @foreach($errors as $error)
                            {{ $error }}
                            @endforeach
                        @endif
                        @error('data.{{$index}}.recurring_qty') 
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <span class="block sm:inline">{{ $message }} </span>
                            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        </div>
                        @enderror
                      </div>
                    </li>
                @endforeach
            @endif
            <!-- One Time Plan Products-->
            @if(!empty($subscription->product_ids))
                @foreach($subscription->service()->products() as $product)
                    <li class="flex py-6">
                      @if(false)
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200">
                        <img src="https://tailwindui.com/img/ecommerce-images/shopping-cart-page-04-product-01.jpg" alt="Salmon orange fabric pouch with match zipper, gray zipper pull, and adjustable hip belt." class="h-full w-full object-cover object-center">
                      </div>
                      @endif
                      <div class="ml-0 flex flex-1 flex-col">
                        <div>
                          <div class="flex justify-between text-base font-medium text-gray-900">
                            <h3>
                              {!! nl2br($product->notes) !!}
                            </h3>
                            <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</p>
                          </div>
                          <p class="mt-1 text-sm text-gray-500"></p>
                        </div>
                        <div class="flex flex-1 items-end justify-between text-sm">
                          <p class="text-gray-500"></p>
                          <div class="flex">
                          </div>
                        </div>
                      </div>
                    </li>
                @endforeach
            @endif
            </ul>
        </div>

        <div class="w-full p-4 md:max-w-3xl">
            <h2 class="text-2xl font-normal text-left border-b-4">Optional products</h2>
        </div>

        <div class="w-full px-4 md:max-w-3xl">

            <!-- Optional Recurring Products-->
            <ul role="list" class="-my-6 divide-y divide-gray-200">
                @if(!empty($subscription->optional_recurring_product_ids))
                    @foreach($subscription->service()->optional_recurring_products() as $index => $product)
                        <li class="flex py-6">
                          @if(false)
                          <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2">
                            <img src="https://tailwindui.com/img/ecommerce-images/shopping-cart-page-04-product-01.jpg" alt="Salmon orange fabric pouch with match zipper, gray zipper pull, and adjustable hip belt." class="h-full w-full object-cover object-center">
                          </div>
                          @endif
                          <div class="ml-0 flex flex-1 flex-col">
                            <div>
                              <div class="flex justify-between text-base font-medium text-gray-900">
                                <h3>{!! nl2br($product->notes) !!}</h3>
                                <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</p>
                              </div>
                              <p class="mt-1 text-sm text-gray-500"></p>
                            </div>
                            <div class="flex content-end text-sm mt-1">
                                <p class="text-gray-500 w-full"></p>
                                <div class="flex place-content-end">
                                    <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                                    <input wire:model="data.{{ $index }}.optional_recurring_qty" type="text" class="w-1/4 rounded-md border-gray-300 shadow-sm sm:text-sm text-center" placeholder="0"/>
                                </div>
                            </div>
                             
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                              <span class="block sm:inline">{{ isset($data[$index]['optional_recurring_qty']) ? $data[$index]['optional_recurring_qty'] : '' }}</span>
                              <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            </div>

                          </div>
                        </li>
                    @endforeach    
                @endif
                @if(!empty($subscription->optional_product_ids))
                    @foreach($subscription->service()->optional_products() as $index => $product)
                        <li class="flex py-6">
                          @if(false)
                          <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2">
                            <img src="https://tailwindui.com/img/ecommerce-images/shopping-cart-page-04-product-01.jpg" alt="Salmon orange fabric pouch with match zipper, gray zipper pull, and adjustable hip belt." class="h-full w-full object-cover object-center">
                          </div>
                          @endif
                          <div class="ml-0 flex flex-1 flex-col">
                            <div>
                              <div class="flex justify-between text-base font-medium text-gray-900">
                                <h3>{!! nl2br($product->notes) !!}</h3>
                                <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</p>
                              </div>
                              <p class="mt-1 text-sm text-gray-500"></p>
                            </div>
                            <div class="flex content-end text-sm mt-1">
                                <p class="text-gray-500 w-full"></p>
                                <div class="flex place-content-end">
                                    <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                                    <input type="text" wire:model="data.{{ $index }}.optional_qty" class="w-1/4 rounded-md border-gray-300 shadow-sm sm:text-sm text-center" placeholder="0">
                                </div>
                            </div>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                              <span class="block sm:inline">{{ isset($data[$index]['optional_qty']) ? $data[$index]['optional_qty'] : '' }}</span>
                              <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            </div>
                          </div>
                        </li>
                    @endforeach    
                @endif
            </ul>
        </div>
    </div>


    <div class="col-span-4 bg-blue-500 flex flex-col item-center p-2 h-screen">
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