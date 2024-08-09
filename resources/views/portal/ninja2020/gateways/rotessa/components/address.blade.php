
<div class="px-4 py-5 border-b border-gray-200 sm:px-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900">
               {{ ctrans('texts.address_information') }}
            </h3>

            <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
                {{ ctrans('texts.enter_information_for_the_account_holder') }}
            </p>
        </div>
    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            {{ ctrans('texts.address1') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="address_1" name="address_1" type="text" placeholder="Address Line 1" required value="{{ old('address_1', $address_1) }}">
        @error('address_1')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            {{ ctrans('texts.address2') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="address_2" name="address_2" type="text" placeholder="Address Line 2" value="{{ old('address_2', $address_2) }}">
        @error('address_2')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
           {{ ctrans('texts.city') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="city" name="city" type="text" placeholder="City" required value="{{ old('city', $city) }}">
        @error('city')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            {{ ctrans('texts.postal_code') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="postal_code" name="postal_code" type="text" placeholder="Postal Code" required value="{{ old('postal_code', $postal_code ) }}">
        @error('postal_code')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            {{ ctrans('texts.country') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            @if('US' == $country)
                <input type="radio" id="us" name="country" value="US" required @checked(old('country', $country) == 'US')>
                <label for="us">{{ ctrans('texts.united_states') }}</label><br>
            @else
                <input type="radio" id="ca" name="country" value="CA" required @checked(old('country', $country) == 'CA')>
                <label for="ca">{{ ctrans('texts.canada') }}</label><br>
            @endif

            @error('country')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
        </dd>
    </div>

    @include("portal.ninja2020.gateways.rotessa.components.dropdowns.country.$country",compact('province_code'))
