<div class="flex flex-col p-10">

    @if (Auth::guard('user')->check())
    <div class="flex mx-auto gap-4">

        <div class="w-7/8 mr-auto">
            <h2 class="text-2xl font-semibold text-gray-800">E-Invoice Beta Phase</h2>
            <p class="py-2">Hey there!</p>
            <p class="py-2">Thanks for joining us on our pilot program for e-invoicing for self hosted users. Our aim is to allow you to send your einvoices through the PEPPOL network via Invoice Ninja.</p>
            <p class="py-2">Our hosted servers will proxy your einvoices into the PEPPOL network for you, and also route einvoices back to you via Webhooks.</p>
            <h3 class="text-2xl font-semibold text-gray-800 py-4">Configuration:</h3>
            <p class="py-2">To start sending einvoices via the PEPPOL network, you are required to create a Legal Entity ID, this will be your network address in the PEPPOL network. The tabled data below is what will be used to register your legal entity, please confirm the details are correct prior to registering.</p>
            <p class="py-2">If you are in a region which requires routing directly to the government, such as Spain, Italy or Romania, you are required to have already registered with your government for the sending of einvoices.</p>
            <p class="py-2">In your .env file, add the variable LICENSE_KEY= with your self hosted white label license key - this is used for authentication with our servers, and to register the sending entity. You will also want to contact us to ensure we have configured your license for this beta test! 
            <p class="py-2">For discussion, help and troubleshooting, please use the slack channel #einvoicing.</p>
        </div>

        <div class="w-1/8 ml-auto">
            <h1 class="text-2xl font-semibold text-gray-800">Welcome, {{ Auth::guard('user')->user()->first_name }}!</h1>
            <div class="flex justify-between">
                <button wire:click="logout" class="w-full flex bg-blue-500 justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Logout
                </button>
            </div>
        </div>
    </div>

    <div class="w-full flex-grow py-10 items-center justify-between">
        
        @if (session()->has('error'))
            <div class="mt-4 text-red-600 text-sm font-semibold">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid lg:grid-cols-3 mx-6 md:mx-0 md:my-2 border border-gray-300 rounded-lg shadow-md  bg-gray-100">

        <div class="font-semibold p-2 bg-gray-200 border-b border-gray-300">Name</div>
        <div class="font-semibold p-2 bg-gray-200 border-b border-gray-300">Legal Entity Id</div>
        <div class="font-semibold p-2 bg-gray-200 border-b border-gray-300">Register</div>

            @foreach($companies as $company)

            <div class="w-full mx-6 md:mx-0  border-b border-gray-300">
                <dl class="grid grid-cols-2 gap-4 mb-4">

                    <div class="flex items-center p-1">
                        <span class="font-semibold text-gray-700">{{ ctrans('texts.name') }}:</span>
                        <span class="ml-2 text-gray-600">{{ $company['party_name'] }}</span>
                    </div>
                
                    <div class="flex items-center p-1">
                        <span class="font-semibold text-gray-700">{{ ctrans('texts.address1') }}:</span>
                        <span class="ml-2 text-gray-600">{{ $company['line1'] }}</span>
                    </div>
                
                    <div class="flex items-center p-1">
                        <span class="font-semibold text-gray-700">{{ ctrans('texts.address2') }}:</span>
                        <span class="ml-2 text-gray-600">{{ $company['line2'] }}</span>
                    </div>
                
                    <div class="flex items-center p-1">
                        <span class="font-semibold text-gray-700">{{ ctrans('texts.city') }}:</span>
                        <span class="ml-2 text-gray-600">{{ $company['city'] }}</span>
                    </div>

                    <div class="flex items-center p-1">
                        <span class="font-semibold text-gray-700">{{ ctrans('texts.state') }}:</span>
                        <span class="ml-2 text-gray-600">{{ $company['county'] }}</span>
                    </div>
                
                    <div class="flex items-center p-1">
                        <span class="font-semibold text-gray-700">{{ ctrans('texts.postal_code') }}:</span>
                        <span class="ml-2 text-gray-600">{{ $company['zip'] }}</span>
                    </div>
                
                    <div class="flex items-center p-1">
                        <span class="font-semibold text-gray-700">{{ ctrans('texts.country') }}:</span>
                        <span class="ml-2 text-gray-600">{{ $company['country'] }}</span>
                    </div>

                    <div class="flex items-center p-1">
                        <span class="font-semibold text-gray-700">{{ ctrans('texts.vat_number') }}</span>
                        <span class="ml-2 text-gray-600">{{ $company['vat_number'] }}</span>
                    </div>
                </dl>
                  
            </div>

            <div class="p-2 border-b border-gray-300">
                {{ $company['legal_entity_id'] }}
            </div>

            <div class="p-2 border-b border-gray-300">
                @if($company['legal_entity_id'])
                    <p>Registered</p>
                @else
                    <button class="bg-blue-500 justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" wire:click="register('{{ $company['key'] }}')" wire:loading.attr="disabled">Register</button>
                @endif
            </div>

            @endforeach
            

        </div>
    </div>
    @else
    <div class="w-full flex items-center justify-center min-h-screen bg-gray-100">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md sm:max-w-sm md:max-w-xs lg:max-w-md xl:max-w-lg">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Login to Your Account</h2>

        <form wire:submit.prevent="login" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" wire:model="email" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" wire:model="password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="w-full flex bg-blue-500 justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Login
                </button>
            </div>
            
        </form>

        @if (session()->has('error'))
            <div class="mt-4 text-red-600 text-sm font-semibold">
                {{ session('error') }}
            </div>
        @endif
        </div>
    </div>
    @endif
</div>