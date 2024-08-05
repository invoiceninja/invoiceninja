<div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
        {{ ctrans('texts.routing_number') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="routing_number" name="routing_number" type="text" placeholder="{{ ctrans('texts.routing_number') }}" required value="{{ old('routing_number', $routing_number) }}">
            @error('routing_number')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror   
    </dd>
</div>

<div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
        {{ ctrans('texts.account_type') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <div class="sm:grid-cols-2 sm:flex">
            <div class="flex items-center px-2">
                <input id="bank_account_type_savings" name="bank_account_type" value="Savings" required @checked(old('bank_account_type', $bank_account_type)) type="radio" class="focus:ring-gray-500 h-4 w-4 border-gray-300 disabled:opacity-75 disabled:cursor-not-allowed">
                <label for="bank_account_type_savings" class="ml-3 block text-sm font-medium cursor-pointer">{{ ctrans('texts.savings') }}</label>
                @error('bank_account_type')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-center px-2">
                <input id="bank_account_type_checking" name="bank_account_type" value="Checking" required @checked(old('bank_account_type', $bank_account_type)) type="radio" class="focus:ring-gray-500 h-4 w-4 border-gray-300 disabled:opacity-75 disabled:cursor-not-allowed">
                <label for="bank_account_type_checking" class="ml-3 block text-sm font-medium cursor-pointer">{{ ctrans('texts.checking') }}</label>
                @error('bank_account_type')
                    ed-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </dd>
</div>
