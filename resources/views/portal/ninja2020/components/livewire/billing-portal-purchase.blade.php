<div class="grid grid-cols-12">
    <div class="col-span-12 lg:col-span-6 bg-gray-50 flex flex-col items-center">
        <div class="w-full p-10 lg:w-1/2 lg:mt-24 lg:p-0">
            <img class="h-8" src="{{ $subscription->company->present()->logo }}"
                 alt="{{ $subscription->company->present()->name }}">

            <div class="mt-6">
                <p
                    class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary text-white">
                    Subscription
                </p>
                <h1 id="billing-page-company-logo" class="text-3xl font-bold tracking-wide">
                    Invoice Ninja Pro+
                </h1>
            </div>

            @if(!empty($subscription->product_ids))
                <div class="flex flex-col mt-8">
                <span
                    class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-full text-xs font-medium">
                    One-time purchases:
                </span>

                    <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                        <div class="text-sm">Pro+ plan</div>
                        <div data-ref="price-and-quantity-container">
                            <span data-ref="price">$10</span>
                            <span data-ref="quantity" class="text-sm">(1x)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                        <div class="text-sm">Another awesome product</div>
                        <div data-ref="price-and-quantity-container">
                            <span data-ref="price">$5</span>
                            <span data-ref="quantity" class="text-sm">(2x)</span>
                        </div>
                    </div>
                </div>
            @endif

            @if(!empty($subscription->recurring_product_ids))
                <div class="flex flex-col mt-8">
                <span
                    class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-full text-xs font-medium">
                    Recurring purchases:
                </span>

                    <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                        <div class="text-sm">Pro+ plan</div>
                        <div data-ref="price-and-quantity-container">
                            <span data-ref="price">$10</span>
                            <span data-ref="quantity" class="text-sm">(1x)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                        <div class="text-sm">Another awesome product</div>
                        <div data-ref="price-and-quantity-container">
                            <span data-ref="price">$5</span>
                            <span data-ref="quantity" class="text-sm">(2x)</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="relative mt-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>

                <div class="relative flex justify-center text-sm leading-5">
                    <h1 class="text-2xl font-bold tracking-wide bg-gray-50 px-6 py-0">
                        {{ ctrans('texts.total') }}: {{ App\Utils\Number::formatMoney(20, $subscription->company) }}
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

                        @foreach($this->methods as $method)
                            <button
                                wire:click="handleMethodSelectingEvent('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}')"
                                class="px-3 py-2 border rounded mr-4 hover:border-blue-600">
                                {{ $method['label'] }}
                            </button>
                        @endforeach
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

                    <div class="flex items-center mt-4">
                        <label class="w-full mr-2">
                            <input type="text" wire:model.lazy="coupon" class="input w-full m-0"/>
                            <small class="block text-gray-900 mt-2">{{ ctrans('texts.billing_coupon_notice') }}</small>
                        </label>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
