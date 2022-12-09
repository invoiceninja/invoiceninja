<div class="grid grid-cols-12">
    <div class="col-span-8 bg-gray-50 flex flex-col max-h-100px items-center h-screen">
        <div class="w-full p-4 md:max-w-3xl">
            <div class="w-full mb-4">
                <img class="object-scale-down" style="max-height: 100px;"src="{{ $subscription->company->present()->logo }}" alt="{{ $subscription->company->present()->name }}">
                <h1 id="billing-page-company-logo" class="text-3xl font-bold tracking-wide mt-6">
                {{ $subscription->name }}
                </h1>
            </div>
            <form wire:submit.prevent="submit">

            <!-- Recurring Plan Products-->
            <ul role="list" class="-my-6 divide-y divide-gray-200">
            @if(!empty($subscription->recurring_product_ids))
                @foreach($recurring_products as $index => $product)
                    <li class="flex py-6">
                      @if(filter_var($product->custom_value1, FILTER_VALIDATE_URL))
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2">
                        <img src="{{$product->custom_value1}}" alt="" class="h-full w-full object-cover object-center">
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

                                    <select wire:model.debounce.300ms="data.{{ $index }}.recurring_qty" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
                                        <option value="1" selected="selected">1</option>
                                        @for ($i = 2; $i <= $subscription->max_seats_limit; $i++)
                                        <option value="{{$i}}">{{$i}}</option>
                                        @endfor
                                    </select>
                            </div>

                            @endif
                        </div>
                        @error("data.{$index}.recurring_qty") 
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
                @foreach($products as $product)
                    <li class="flex py-6">
                      @if(filter_var($product->custom_value1, FILTER_VALIDATE_URL))
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200">
                        <img src="{{$product->custom_value1}}" alt="" class="h-full w-full object-cover object-center">
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

        @if(!empty($subscription->optional_recurring_product_ids) || !empty($subscription->optional_product_ids))
        <div class="w-full p-4 md:max-w-3xl">
            <h2 class="text-2xl font-normal text-left border-b-4">Optional products</h2>
        </div>
        @endif
        <div class="w-full px-4 md:max-w-3xl">

            <!-- Optional Recurring Products-->
            <ul role="list" class="-my-6 divide-y divide-gray-200">
                @if(!empty($subscription->optional_recurring_product_ids))
                    @foreach($optional_recurring_products as $index => $product)
                        <li class="flex py-6">
                      @if(filter_var($product->custom_value1, FILTER_VALIDATE_URL))
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200">
                        <img src="{{$product->custom_value1}}" alt="" class="h-full w-full object-cover object-center">
                      </div>
                      @endif
                          <div class="ml-0 flex flex-1 flex-col">
                            <div>
                              <div class="flex justify-between text-base font-medium text-gray-900">
                                <h3>{!! nl2br($product->notes) !!}</h3>
                                <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }} </p>
                              </div>
                              
                            </div>
                            <div class="flex justify-between text-sm mt-1">
                                @if(is_numeric($product->custom_value2))
                                <p class="text-gray-500 w-3/4"></p>
                                <div class="flex place-content-end">
                                    @if($subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                    <p class="w-full text-sm font-light text-red-500 text-right mr-2 mt-2">Out of stock</p>
                                    @else
                                    <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                                    @endif
                                    <select wire:model.debounce.300ms="data.{{ $index }}.optional_recurring_qty" class="rounded-md border-gray-300 shadow-sm sm:text-sm" 
                                        @if($subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                        disabled 
                                        @endif
                                        >
                                        <option value="0" selected="selected">0</option>
                                        @for ($i = 1; $i <= ($subscription->use_inventory_management ? min($product->in_stock_quantity,$product->custom_value2) : $product->custom_value2); $i++)
                                        <option value="{{$i}}">{{$i}}</option>
                                        @endfor
                                    </select>
                                </div>
                                @endif
                            </div>
                          </div>
                        </li>
                    @endforeach    
                @endif
                @if(!empty($subscription->optional_product_ids))
                    @foreach($optional_products as $index => $product)
                        <li class="flex py-6">
                      @if(filter_var($product->custom_value1, FILTER_VALIDATE_URL))
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200">
                        <img src="{{$product->custom_value1}}" alt="" class="h-full w-full object-cover object-center">
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
                            <div class="flex justify-between text-sm mt-1">
                                @if(is_numeric($product->custom_value2))
                                <p class="text-gray-500 w-3/4"></p>
                                <div class="flex place-content-end">
                                    @if($subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                    <p class="w-full text-sm font-light text-red-500 text-right mr-2 mt-2">Out of stock</p>
                                    @else
                                    <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                                    @endif
                                    <select wire:model.debounce.300ms="data.{{ $index }}.optional_qty" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
                                        <option value="0" selected="selected">0</option>
                                        @for ($i = 1; $i <= ($subscription->use_inventory_management ? min($product->in_stock_quantity,$product->custom_value2) : $product->custom_value2); $i++)
                                        <option value="{{$i}}">{{$i}}</option>
                                        @endfor
                                    </select>
                                </div>

                                @endif
                            </div>
                          </div>
                        </li>
                    @endforeach    
                @endif
            </ul>
        </div>
    </div>

    </form>

    <div class="col-span-4 bg-blue-500 flex flex-col item-center p-2 h-screen" wire:init="buildBundle">
        <div class="w-full p-4">
            <div id="summary" class="px-4 text-white">
                <h1 class="font-semibold text-2xl border-b-2 border-gray-200 border-opacity-50 pb-2 text-white">{{ ctrans('texts.order') }}</h1>

                @foreach($bundle as $item)
                    <div class="flex justify-between mt-1 mb-1">
                      <span class="font-light text-sm uppercase">{{$item['product']}} x {{$item['qty']}}</span>
                      <span class="font-semibold text-sm">{{ $item['price'] }}</span>
                    </div>
                @endforeach

                @if(!empty($subscription->promo_code) && !$subscription->trial_enabled)
                    <form wire:submit.prevent="handleCoupon" class="">
                    @csrf
                        <div class="mt-4">
                          <label for="coupon" class="block text-sm font-medium text-white">{{ ctrans('texts.promo_code') }}</label>
                          <div class="mt-1 flex rounded-md shadow-sm">
                            <div class="relative flex flex-grow items-stretch focus-within:z-10">
                              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                              </div>
                              <input type="text" wire:model.defer="coupon" class="block w-full rounded-none rounded-l-md border-gray-300 pl-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" placeholder="">
                            </div>
                            <button class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                              
                              <span>{{ ctrans('texts.apply') }}</span>
                            </button>
                          </div>
                        </div>
                    </form>
                @endif

                <div class="border-t-2 border-gray-200 border-opacity-50 mt-4">
                    @if($discount)
                    <div class="flex font-semibold justify-between py-1 text-sm uppercase">
                        <span>{{ ctrans('texts.subtotal') }}</span>
                        <span>{{ $sub_total }}</span>
                    </div>
                    <div class="flex font-semibold justify-between py-1 text-sm uppercase">
                        <span>{{ ctrans('texts.discount') }}</span>
                        <span>{{ $discount }}</span>
                    </div>
                    @endif
                    <div class="flex font-semibold justify-between py-1 text-sm uppercase border-t-2">
                        <span>{{ ctrans('texts.total') }}</span>
                        <span>{{ $total }}</span>
                    </div>

                    @if($authenticated)
                    <button class="bg-white font-semibold hover:bg-gray-600 py-3 text-sm text-blue-500 uppercase w-full">Checkout</button>
                    @else
                    <form wire:submit.prevent="handleEmail" class="">
                    @csrf
                        <div class="mt-4">
                          <label for="email" class="block text-sm font-medium text-white">{{ ctrans('texts.email') }}</label>
                          <div class="mt-1 flex rounded-md shadow-sm">
                            <div class="relative flex flex-grow items-stretch focus-within:z-10">
                              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                              </div>
                              <input type="text" wire:model.defer="email" class="block w-full rounded-none rounded-l-md border-gray-300 pl-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" placeholder="">
                            </div>
                            <button class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                              
                              <span>{{ ctrans('texts.login') }}</span>
                            </button>
                          </div>
                            @error("email") 
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                                <span class="block sm:inline">{{ $message }} </span>
                                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            </div>
                            @enderror
                            
                        </div>
                    </form>
                    @endif

                    @if($email && !$errors->has('email'))
                    <div class="py-6 px-6 w-80 border mx-auto text-center my-6">
                        <form wire:submit.prevent="handleLogin" class="" x-data="otpForm()">
                            <p class="mb-4">{{ ctrans('texts.otp_code_message')}}</p>
                            <div class="flex justify-between">
                              <template x-for="(input, index) in length" :key="index">
                                <input
                                    type="text"
                                    maxlength="1"
                                    class="border border-gray-500 w-10 h-10 text-center text-gray-700"
                                    :x-ref="index"
                                    x-on:input="handleInput($event)"
                                    x-on:paste="handlePaste($event)"
                                    x-on:keydown.backspace="$event.target.value || handleBackspace($event.target.getAttribute('x-ref'))"
                                />
                              </template>
                            </div>
                        </form>
                        @error("login") 
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                                <span class="block sm:inline">{{ $message }} </span>
                                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            </div>
                        @enderror
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
<script>
    function otpForm() {
        return {
            length: 6,
            login: "",

            handleInput(e) {
                const input = e.target;

                this.login = Array.from(Array(this.length), (element, i) => {
                return this.$refs[i].value || "";
                }).join("");

                if (input.nextElementSibling && input.value) {
                    input.nextElementSibling.focus();
                    input.nextElementSibling.select();
                }

                if(this.login.length == 6){
                    this.$wire.handleLogin(this.login);
                }

            },

            handlePaste(e) {
                const paste = e.clipboardData.getData('text');
                this.value = paste;

                const inputs = Array.from(Array(this.length));

                inputs.forEach((element, i) => {
                    this.$refs[i].value = paste[i] || '';
                });
            },

            handleBackspace(e) {
                const previous = parseInt(e, 10) - 1;
                this.$refs[previous] && this.$refs[previous].focus();
                this.$wire.loginValidation(); 
            },
      };
    }
</script>
</div>

