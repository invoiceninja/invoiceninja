
<div class="px-4 py-5 border-b border-gray-200 sm:px-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900">
               Address Information
            </h3>

            <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
                Enter the address information for the account holder
            </p>
        </div>
    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Address Line 1
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="address_1" name="address_1" type="text" placeholder="Address Line 1" required value="{{ old('address_1', $address_1) }}">
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Address Line 2
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="address_2" name="address_2" type="text" placeholder="Address Line 2" required value="{{ old('address_2', $address_2) }}">
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            City
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="city" name="city" type="text" placeholder="City" required value="{{ old('city', $city) }}">
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Postal Code
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="postal_code" name="postal_code" type="text" placeholder="Postal Code" required value="{{ old('postal_code', $postal_code ) }}">
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Country
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            @if('US' == $country)
                <input type="radio" id="us" name="country" value="US" required @checked(old('country', $country) == 'US')>
                <label for="us">United States</label><br>
            @else
                <input type="radio" id="ca" name="country" value="CA" required @checked(old('country', $country) == 'CA')>
                <label for="ca">Canada</label><br>
            @endif
        </dd>
    </div>

    @include("portal.ninja2020.gateways.rotessa.components.dropdowns.country.$country",compact('province_code'))