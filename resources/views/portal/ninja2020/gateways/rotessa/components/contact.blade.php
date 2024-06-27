
<div class="px-4 py-5 border-b border-gray-200 sm:px-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900">
                Account Holder Information
            </h3>

            <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
                Enter the information for the account holder
            </p>
        </div>

<div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Full Name
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="name" name="name" type="text" placeholder="Full Name" required value="{{ old('name',$name) }}">
        </dd>
    </div>


    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Email Address
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" name="email" id="email" type="email" placeholder="Email Address" required value="{{ old('email',$email) }}">
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Home Phone
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="home_phone" name="home_phone" type="text" placeholder="Home Phone" required value="{{ old('phone',$phone) }}">
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Other Phone
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <input class="input w-full" id="phone" name="phone" type="text" placeholder="Phone" required value="{{ old('phone',$phone) }}">
        </dd>
    </div>

    <div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            Customer Type
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <div class="sm:grid-cols-2 sm:flex">
            <div class="flex items-center px-2">
                <input id="customer_type_personal" name="customer_type" value="Personal" required @checked(old('customer_type', $customer_type) == 'Personal') type="radio" class="focus:ring-gray-500 h-4 w-4 border-gray-300 disabled:opacity-75 disabled:cursor-not-allowed">
                <label for="customer_type_personal" class="ml-3 block text-sm font-medium cursor-pointer">Personal</label>
            </div>
            <div class="flex items-center px-2">
                <input id="customer_type_business" name="customer_type" value="Business" required @checked(old('customer_type', $customer_type) == 'Business') type="radio" class="focus:ring-gray-500 h-4 w-4 border-gray-300 disabled:opacity-75 disabled:cursor-not-allowed">
                <label for="customer_type_business" class="ml-3 block text-sm font-medium cursor-pointer">Business</label>
            </div>
        </div>
        </dd>
    </div>


    <input name="id" type="hidden" value="{{ old('id',  $id ) }}">
        <input name="custom_identifier" type="hidden" value="{{ old('custom_identifer', $contact['custom_identifier']) }}">