<div class="grid grid-cols-12">
    <div class="col-span-12 xl:col-span-8 bg-gray-50 flex flex-col items-center">
        <div class="w-full p-10 lg:mt-24 md:max-w-3xl">
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
                        {{ ctrans('texts.one_time_purchases') }}
                    </p>

                    @foreach($subscription->service()->products() as $product)
                        <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                            <div>
                                <p class="text-sm text-gray-800">{!! nl2br($product->notes) !!}</p>
                            </div>
                            <div data-ref="price-and-quantity-container">
                                <span
                                    data-ref="price">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($subscription->recurring_product_ids))
                <div class="flex flex-col mt-8">
                    <p
                        class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-full text-xs font-medium">
                        {{ ctrans('texts.recurring_purchases') }}
                    </p>

                    @foreach($subscription->service()->recurring_products() as $product)
                        <div class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border">
                            <div class="text-sm">{!! nl2br($product->notes) !!}</div>
                            <div data-ref="price-and-quantity-container">
                                <span
                                    data-ref="price">{{ \App\Utils\Number::formatMoney($product->price, $subscription->company) }}</span>
                                {{--                                <span data-ref="quantity" class="text-sm">(1x)</span>--}}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif


            @if($subscription->per_seat_enabled && $subscription->max_seats_limit > 1)
                <div class="flex mt-4 space-x-4 items-center">
                    <span class="text-sm mx-2">{{ ctrans('texts.qty') }}:</span>
                    <button wire:click="updateQuantity('decrement')" class="bg-gray-100 border rounded p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="feather feather-minus">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                    <div class="px-2">{{ $quantity }}</div>
                    <button wire:click="updateQuantity('increment')" class="bg-gray-100 border rounded p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="feather feather-plus">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                </div>
            @endif

            <div class="relative mt-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>

                <div class="relative flex justify-center text-sm leading-5">
                    <h1 class="text-2xl font-bold tracking-wide bg-gray-50 px-6 py-0">
                        {{ ctrans('texts.total') }}
                        : {{ \App\Utils\Number::formatMoney($price, $subscription->company) }}

                        @if($steps['discount_applied'])
                            <small
                                class="ml-1 line-through text-gray-500">{{ \App\Utils\Number::formatMoney($subscription->price, $subscription->company) }}</small>
                        @endif
                    </h1>
                </div>
            </div>

            @if($subscription->service()->getPlans()->count() > 1)
                <div class="flex flex-col mt-10">
                    <p class="mb-4 uppercase leading-4 tracking-wide inline-flex items-center rounded-full text-xs font-medium">
                        {{ ctrans('texts.you_might_be_interested_in_following') }}:
                    </p>

                    <div class="mt-4">
                        @foreach($subscription->service()->getPlans() as $_subscription)
                            <button class="mt-8 mr-2">
                                <a class="border mt-4 bg-white rounded py-2 px-4 hover:bg-gray-100 text-sm" href="{{ route('client.subscription.purchase', $_subscription->hashed_id) }}">{{ $_subscription->name }}</a>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="col-span-12 xl:col-span-4 bg-white flex flex-col items-center lg:h-screen">
        <div class="w-full p-10 md:p-24 xl:mt-32 md:max-w-3xl">
            <div class="col-span-12 w-full xl:col-span-9">
                <h2 class="text-2xl font-bold tracking-wide">{{ $heading_text ?? ctrans('texts.checkout') }}</h2>
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
                            <input type="hidden" name="contact_first_name" value="{{ $contact->first_name }}">
                            <input type="hidden" name="contact_last_name" value="{{ $contact->last_name }}">
                            <input type="hidden" name="contact_email" value="{{ $contact->email }}">
                            <input type="hidden" name="client_city" value="{{ $contact->client->city }}">
                            <input type="hidden" name="client_postal_code" value="{{ $contact->client->postal_code }}">
                            
                        </form>

                        @if($steps['started_payment'] == false)
                            @foreach($this->methods as $method)
                                <button
                                    wire:click="handleMethodSelectingEvent('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}', '{{ $method['is_paypal'] }}'); $wire.$refresh(); "
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
                @elseif(!$steps['payment_required'])
                    <form wire:submit="handlePaymentNotRequired" class="mt-8">
                        @csrf
                        <button class="px-3 py-2 border rounded mr-4 hover:border-blue-600">
                            {{ ctrans('texts.click_to_continue') }}
                        </button>
                    </form>
                @elseif($steps['show_start_trial'])
                    <form wire:submit="handleTrial" class="mt-8">
                        @csrf
                        <button class="px-3 py-2 border rounded mr-4 hover:border-blue-600">
                            {{ ctrans('texts.trial_call_to_action') }}
                        </button>
                    </form>
                @elseif($steps['check_rff'])
                    @if($errors->any())
                    <div class="alert alert-failure mb-4">
                        @foreach($errors->all() as $error)
                          <p>{{ $error }}</p>
                        @endforeach
                    </div>
                    @endif

                    <form wire:submit="handleRff">
                        @csrf

                        @if(strlen($contact->first_name ?? '') === 0)
                        <div class="col-auto mt-3">
                            <label for="first_name" class="input-label">{{ ctrans('texts.first_name') }}</label>
                            <input id="first_name" class="input w-full" wire:model="contact_first_name" />
                        </div>
                        @endif

                        @if(strlen($contact->last_name ?? '') === 0)
                        <div class="col-auto mt-3 @if($contact->last_name) !== 0) hidden @endif">
                            <label for="last_name" class="input-label">{{ ctrans('texts.last_name') }}</label>
                            <input id="last_name" class="input w-full" wire:model="contact_last_name" />
                        </div>
                        @endif

                        @if(strlen($contact->email ?? '') === 0)
                        <div class="col-auto mt-3 @if($contact->email) !== 0) hidden @endif">
                            <label for="email" class="input-label">{{ ctrans('texts.email') }}</label>
                            <input id="email" class="input w-full" wire:model="contact_email" />
                        </div>
                        @endif

                        @if(strlen($client_postal_code ?? '') === 0)
                        <div class="col-auto mt-3 @if($client_postal_code) !== 0) hidden @endif">
                            <label for="postal_code" class="input-label">{{ ctrans('texts.postal_code') }}</label>
                            <input id="postal_code" class="input w-full" wire:model="client_postal_code" />
                        </div>
                        @endif

                        @if(strlen($client_city ?? '') === 0)
                        <div class="col-auto mt-3 @if($client_city) !== 0) hidden @endif">
                            <label for="city" class="input-label">{{ ctrans('texts.city') }}</label>
                            <input id="city" class="input w-full" wire:model="client_city" />
                        </div>
                        @endif

                        <button 
                            type="submit"
                            class="button button-block bg-primary text-white mt-4">
                            {{ ctrans('texts.next') }}
                        </button>
                    </form>
                @else
                    <form wire:submit="authenticate" class="mt-8">
                        @csrf

                        <label for="email_address">
                            <span class="input-label">{{ ctrans('texts.email_address') }}</span>
                            <input wire:model="email" type="email" class="input w-full"/>

                            @error('email')
                            <p class="validation validation-fail block w-full" role="alert">
                                {{ $message }}
                            </p>
                            @enderror
                        </label>

                        @if($steps['existing_user'])
                            <label for="password" class="block mt-2">
                                <span class="input-label">{{ ctrans('texts.password') }}</span>
                                <input wire:model="password" type="password" class="input w-full" autofocus/>

                                @error('password')
                                <p class="validation validation-fail block w-full" role="alert">
                                    {{ $message }}
                                </p>
                                @enderror
                            </label>

                            <button wire:loading.attr="disabled" type="button" wire:click="passwordlessLogin"
                                    class="mt-4 text-sm active:outline-none focus:outline-none">
                                {{ ctrans('texts.login_without_password') }}
                            </button>

                            @if($steps['passwordless_login_sent'])
                                <span
                                    class="block mt-2 text-sm text-emerald-600">{!! ctrans('texts.sent') !!}</span>
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
                            <span class="px-2 text-gray-700 bg-white">{{ ctrans('texts.promo_code') }}</span>
                        </div>
                    </div>

                    <form wire:submit="handleCoupon" class="flex items-center mt-4">
                        @csrf

                        <label class="w-full mr-2">
                            <input type="text" wire:model="coupon" class="input w-full m-0"/>
                        </label>

                        <button class="button button-primary bg-primary">{{ ctrans('texts.apply') }}</button>
                    </form>
                @endif

                @if($steps['not_eligible'] && !is_null($steps['not_eligible']))
                    <h1>{{ ctrans('texts.payment_error') }}</h1>

                    @if($steps['not_eligible_message'])
                        <small class="mt-4 block">{{ $steps['not_eligible_message'] }}</small>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
