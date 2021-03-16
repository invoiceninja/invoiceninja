<div class="grid grid-cols-12">
    <!-- Left side with payment/product information. -->
    <div class="col-span-12 lg:col-span-6 bg-gray-50 shadow-lg lg:h-screen flex flex-col items-center">
        <div class="w-full p-10 lg:w-1/2 lg:mt-48 lg:p-0">
            <h1 class="text-3xl font-bold tracking-wide">Summary</h1>
            <p class="text-gray-800 tracking-wide text-sm">A brief overview of the order</p>

            <p class="my-6">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Culpa earum eos explicabo labore
                laboriosam numquam officia pariatur recusandae repellat. Aliquam aliquid amet dignissimos facere iste,
                provident sed voluptas! Consequuntur ea expedita magnam maiores nisi rem saepe suscipit. At autem,
                expedita explicabo fugiat ipsam maiores modi, odit quae quia quos, voluptatum!</p>

            <span class="text-sm uppercase font-bold">Total:</span>
            <h1 class="text-2xl font-bold tracking-wide">$4,000</h1>

            <a href="#" class="block mt-16 inline-flex items-center space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="feather feather-arrow-left">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>

                <span>Go back</span>
            </a>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-6 bg-white lg:shadow-lg lg:h-screen">
        <div class="grid grid-cols-12 flex flex-col p-10 lg:mt-48 lg:ml-16">
            <div class="col-span-12 w-full lg:col-span-6">
                <h2 class="text-2xl font-bold tracking-wide">{{ $heading_text }}</h2>
                @if (session()->has('message'))
                    @component('portal.ninja2020.components.message')
                        {{ session('message') }}
                    @endcomponent
                @endif

                @if($this->steps['fetched_payment_methods'])
                    <div class="flex items-center mt-4 text-sm">
                        <form action="{{ route('client.payments.process', ['hash' => $hash, 'sidebar' => 'hidden']) }}"
                              method="post"
                              id="payment-method-form">
                            @csrf

                            @if($invoice instanceof \App\Models\Invoice)
                                <input type="hidden" name="invoices[]" value="{{ $invoice->hashed_id }}">
                                <input type="hidden" name="payable_invoices[0][amount]"
                                       value="{{ $invoice->partial > 0 ?  \App\Utils\Number::formatValue($invoice->partial, $invoice->client->currency()) : \App\Utils\Number::formatValue($invoice->balance, $invoice->client->currency()) }}">
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
                                class="p-4 border rounded mr-4 hover:border-blue-600">
                                {{ $method['label'] }}
                            </button>
                        @endforeach
                    </div>
                @else
                    <form wire:submit.prevent="authenticate" class="mt-8">
                        @csrf

                        <label for="email_address">
                            <span class="input-label">E-mail address</span>
                            <input wire:model.defer="email" type="email" class="input w-full"/>

                            @error('email')
                            <p class="validation validation-fail block w-full" role="alert">
                                {{ $message }}
                            </p>
                            @enderror
                        </label>

                        @if($steps['existing_user'])
                            <label for="password" class="block mt-2">
                                <span class="input-label">Password</span>
                                <input wire:model.defer="password" type="password" class="input w-full" autofocus/>

                                @error('password')
                                <p class="validation validation-fail block w-full" role="alert">
                                    {{ $message }}
                                </p>
                                @enderror
                            </label>
                        @endif

                        <button type="submit" class="button button-block bg-primary text-white mt-4">Next</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
