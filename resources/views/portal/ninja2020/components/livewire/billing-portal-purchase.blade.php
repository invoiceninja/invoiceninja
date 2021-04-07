<div class="grid grid-cols-12">
    <div class="col-span-12 lg:col-span-6 bg-gray-50 flex flex-col items-center">
        <div class="w-full p-10 lg:w-1/2 lg:mt-24 lg:p-0">
            <img class="h-8" src="{{ $subscription->company->present()->logo }}"
                 alt="{{ $subscription->company->present()->name }}">

            <div class="mt-6">
                <h1 id="billing-page-company-logo" class="text-3xl font-bold tracking-wide">
                    {{ $subscription->name }}
                </h1>
            </div>

            @if(!empty($subscription->product_ids))
                <div class="flex flex-col mt-8">
                    <p
                        class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-full text-xs font-medium">
                        One-time purchases:
                    </p>

                    @foreach($subscription->service()->products() as $product)
                        <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                            <div>
                                <p class="text-sm text-xl">{{ $product->product_key }}</p>
                                <p class="text-sm text-gray-800">{{ $product->notes }}</p>
                            </div>
                            <div data-ref="price-and-quantity-container">
                                <span
                                    data-ref="price">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</span>
                                {{--                                <span data-ref="quantity" class="text-sm">(1x)</span>--}}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($subscription->recurring_product_ids))
                <div class="flex flex-col mt-8">
                    <p
                        class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-full text-xs font-medium">
                        Recurring purchases:
                    </p>

                    @foreach($subscription->service()->recurring_products() as $product)
                        <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                            <div class="text-sm">{{ $product->product_key }}</div>
                            <div data-ref="price-and-quantity-container">
                                <span
                                    data-ref="price">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</span>
                                {{--                                <span data-ref="quantity" class="text-sm">(1x)</span>--}}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="relative mt-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>

                <div class="relative flex justify-center text-sm leading-5">
                    <h1 class="text-2xl font-bold tracking-wide bg-gray-50 px-6 py-0">
                        {{ ctrans('texts.total') }}: {{ \App\Utils\Number::formatMoney($price, $subscription->company) }}

                        @if($steps['discount_applied'])
                            <small class="ml-1 line-through text-gray-500">{{ \App\Utils\Number::formatMoney($subscription->price, $subscription->company) }}</small>
                        @endif
                    </h1>
                </div>
            </div>

            @if(auth('contact')->user())
                <a href="{{ route('client.invoices.index') }}" class="block mt-16 inline-flex items-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="feather feather-arrow-left">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>

                    <span>{{ ctrans('texts.client_portal') }}</span>
                </a>
            @endif
        </div>
    </div>

    <div class="col-span-12 lg:col-span-6 bg-white lg:h-screen">
        <div class="grid grid-cols-12 flex flex-col p-10 lg:mt-48 lg:ml-16">
            <div class="col-span-12 w-full lg:col-span-6">
                <h2 class="text-2xl font-bold tracking-wide">{{ $heading_text ?? ctrans('texts.login') }}</h2>
                @if (session()->has('message'))
                    @component('portal.ninja2020.components.message')
                        {{ session('message') }}
                    @endcomponent
                @endif

                @if($steps['fetched_payment_methods'])
                    <div class="flex items-center mt-4 text-sm">
                        <form action="{{ route('client.payments.process', ['hash' => $hash, 'sidebar' => 'hidden']) }}"
                              method="post"
                              id="payment-method-form">
                            @csrf

                            @if($invoice instanceof \App\Models\Invoice)
                                <input type="hidden" name="invoices[]" value="{{ $invoice->hashed_id }}">
                                <input type="hidden" name="payable_invoices[0][amount]"
                                       value="{{ $invoice->partial > 0 ? \App\Utils\Number::formatValue($invoice->partial, $invoice->client->currency()) : \App\Utils\Number::formatValue($invoice->balance, $invoice->client->currency()) }}">
                                <input type="hidden" name="payable_invoices[0][invoice_id]"
                                       value="{{ $invoice->hashed_id }}">
                            @endif

                            <input type="hidden" name="action" value="payment">
                            <input type="hidden" name="company_gateway_id" value="{{ $company_gateway_id }}"/>
                            <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}"/>
                        </form>

                        @if($steps['started_payment'] == false)
                            @foreach($this->methods as $method)
                                <button
                                    wire:click="handleMethodSelectingEvent('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}')"
                                    class="px-3 py-2 border rounded mr-4 hover:border-blue-600">
                                    {{ $method['label'] }}
                                </button>
                            @endforeach
                        @endif

                        @if($steps['started_payment'] && $steps['show_loading_bar'])
                            <svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg"
                                 fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        @endif
                    </div>
                @elseif($steps['show_start_trial'])
                    <form wire:submit.prevent="handleTrial" class="mt-8">
                        @csrf
                        <p class="mb-4">Some text about the trial goes here. Details about the days, etc.</p>


                        <button class="px-3 py-2 border rounded mr-4 hover:border-blue-600">
                            {{ ctrans('texts.trial_call_to_action') }}
                        </button>
                    </form>

                @else
                    <form wire:submit.prevent="authenticate" class="mt-8">
                        @csrf

                        <label for="email_address">
                            <span class="input-label">{{ ctrans('texts.email_address') }}</span>
                            <input wire:model.defer="email" type="email" class="input w-full"/>

                            @error('email')
                            <p class="validation validation-fail block w-full" role="alert">
                                {{ $message }}
                            </p>
                            @enderror
                        </label>

                        @if($steps['existing_user'])
                            <label for="password" class="block mt-2">
                                <span class="input-label">{{ ctrans('texts.password') }}</span>
                                <input wire:model.defer="password" type="password" class="input w-full" autofocus/>

                                @error('password')
                                <p class="validation validation-fail block w-full" role="alert">
                                    {{ $message }}
                                </p>
                                @enderror
                            </label>

                            <button wire:loading.attr="disabled" type="button" wire:click="passwordlessLogin"
                                    class="mt-4 text-sm active:outline-none focus:outline-none">
                                Log in without password
                            </button>

                            @if($steps['passwordless_login_sent'])
                                <span
                                    class="block mt-2 text-sm text-green-600">E-mail sent. Please check your inbox!</span>
                            @endif
                        @endif

                        <button type="submit"
                                class="button button-block bg-primary text-white mt-4">{{ ctrans('texts.next') }}</button>
                    </form>
                @endif

                @if(!empty($subscription->promo_code) && !$subscription->trial_enabled)
                    <div class="relative mt-8">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>

                        <div class="relative flex justify-center text-sm leading-5">
                            <span class="px-2 text-gray-700 bg-white">Have a coupon code?</span>
                        </div>
                    </div>

                    <form wire:submit.prevent="handleCoupon" class="flex items-center mt-4">
                        @csrf

                        <label class="w-full mr-2">
                            <input type="text" wire:model.lazy="coupon" class="input w-full m-0"/>
                        </label>

                        <button class="button button-primary bg-primary">Apply</button>
                    </form>
                @endif

                @if($steps['not_eligible'] && !is_null($steps['not_eligible']))
                    <h1>{{ ctrans('texts.payment_error') }}</h1>
                @endif
            </div>
        </div>
    </div>
</div>
