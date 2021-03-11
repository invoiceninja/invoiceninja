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
                <h2 class="text-2xl font-bold tracking-wide">Payment details</h2>

                @if (session()->has('message'))
                    @component('portal.ninja2020.components.message')
                        {{ session('message') }}
                    @endcomponent
                @endif

                @if($authenticated)
                    <div class="flex items-center space-x-4 mt-4 text-sm">
                        <button class="pb-2 border-b-2 border-blue-600">Credit card</button>
                        <button class="pb-2 border-b-2 border-transparent hover:border-blue-600">Bank transfer</button>
                        <button class="pb-2 border-b-2 border-transparent hover:border-blue-600">PayPal</button>
                    </div>
                @endif

                <form wire:submit.prevent="authenticate" class="mt-8">
                    @csrf

                    <label for="email_address">
                        <span class="input-label">E-mail address</span>
                        <input wire:model="email" type="email" class="input w-full"/>

                        @error('email')
                            <p class="validation validation-fail block w-full" role="alert">
                                {{ $message }}
                            </p>
                        @enderror
                    </label>

                    @if($steps['existing_user'])
                        <label for="password" class="block mt-2">
                            <span class="input-label">Password</span>
                            <input wire:model="password" type="password" class="input w-full"/>

                            @error('password')
                                <p class="validation validation-fail block w-full" role="alert">
                                    {{ $message }}
                                </p>
                            @enderror
                        </label>
                    @endif

                    <button type="submit" class="button button-block bg-primary text-white mt-4">Next</button>
                </form>
            </div>
        </div>
    </div>
</div>
