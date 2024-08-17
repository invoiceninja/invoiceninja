<div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                   {{ ctrans('texts.add_bank_account') }}
                </h3>

                <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
                     {{ ctrans('texts.enter_the_information_for_the_bank_account') }} 
                </p>
            </div>
            <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            {{ ctrans('texts.bank_name') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="bank_name" name="bank_name" type="text" placeholder="{{ ctrans('texts.bank_name') }}" required value="{{ old('bank_name', $bank_name) }}">
        
        @error('bank_name')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
             {{ ctrans('texts.account_number') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="account_number" name="account_number" type="text" placeholder="{{ ctrans('texts.account_number') }}" required value="{{ old('account_number', $account_number) }}">
        @error('account_number')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
        </dd>
    </div>


    <input type="hidden" name="authorization_type" id="authorization_type" value="{{ old('authorization_type',$authorization_type) }}" >

@include("portal.ninja2020.gateways.rotessa.components.banks.$country.bank", compact('bank_account_type','routing_number','institution_number','transit_number'))
