@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.register'))

@section('body')
<div class="grid lg:grid-cols-12 bg-gray-100 py-8">
    <div class="col-span-4 col-start-5">
        <h1 class="text-center text-3xl">{{ ctrans('texts.register') }}</h1>
        <p class="block text-center text-gray-600">{{ ctrans('texts.register_label') }}</p>

        <form action="{{ route('client.register', request()->route('company_key')) }}" method="post">
            @csrf

            <!-- Personal info, first name, last name, e-mail address .. -->
            <h3 class="text-lg font-medium leading-6 text-gray-900 mt-8">{{ ctrans('texts.profile') }}</h3>
            <p class="mt-1 text-sm leading-5 text-gray-500">
                {{ ctrans('texts.client_information_text') }}
            </p>
            <div class="shadow overflow-hidden rounded mt-4">
                <div class="px-4 py-5 bg-white sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-3">
                            <section class="flex items-center">
                                <label for="first_name" class="input-label">{{ ctrans('texts.first_name') }}</label>
                                <section class="text-red-400 ml-1 text-sm">*</section>
                            </section>
                            <input id="first_name" class="input w-full" name="first_name" />
                            @error('first_name')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <section class="flex items-center">
                                <label for="last_name" class="input-label">{{ ctrans('texts.last_name') }}</label>
                                <section class="text-red-400 ml-1 text-sm">*</section>
                            </section>
                            <input id="last_name" class="input w-full" name="last_name" />
                            @error('last_name')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <section class="flex items-center">
                                <label for="email_address" class="input-label">{{ ctrans('texts.email_address') }}</label>
                                <section class="text-red-400 ml-1 text-sm">*</section>
                            </section>
                            <input id="email_address" class="input w-full" type="email" name="email" />
                            @error('email')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <section class="flex items-center">
                                <label for="phone" class="input-label">{{ ctrans('texts.phone') }}</label>
                                <section class="text-red-400 ml-1 text-sm">*</section>
                            </section>
                            <input id="phone" class="input w-full" name="phone" />
                            @error('phone')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-6 lg:col-span-3">
                            <section class="flex items-center">
                                <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                                <section class="text-red-400 ml-1 text-sm">*</section>
                            </section>
                            <input id="password" class="input w-full" name="password" type="password" />
                            @error('password')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3 lg:col-span-3">
                            <section class="flex items-center">
                                <label for="password_confirmation" class="input-label">{{ ctrans('texts.confirm_password') }}</label>
                                <section class="text-red-400 ml-1 text-sm">*</section>
                            </section>
                            <input id="state" class="input w-full" name="password_confirmation" type="password" />
                            @error('password_confirmation')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Name, website -->
            <h3 class="text-lg font-medium leading-6 text-gray-900 mt-8">{{ ctrans('texts.website') }}</h3>
            <p class="mt-1 text-sm leading-5 text-gray-500">
                {{ ctrans('texts.make_sure_use_full_link') }}
            </p>
            <div class="shadow overflow-hidden rounded mt-4">
                <div class="px-4 py-5 bg-white sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-3">
                            <label for="street" class="input-label">@lang('texts.name')</label>
                            <input id="name" class="input w-full" name="name" />
                            @error('name')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="website" class="input-label">@lang('texts.website')</label>
                            <input id="website" class="input w-full" name="last_name" />
                            @error('website')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Client personal address -->
            <h3 class="text-lg font-medium leading-6 text-gray-900 mt-8">{{ ctrans('texts.personal_address') }}</h3>
            <p class="mt-1 text-sm leading-5 text-gray-500">
                {{ ctrans('texts.enter_your_personal_address') }}
            </p>
            <div class="shadow overflow-hidden rounded mt-4">
                <div class="px-4 py-5 bg-white sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-4">
                            <label for="address1" class="input-label">@lang('texts.address1')</label>
                            <input id="address1" class="input w-full" name="address1" />
                            @error('address1')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="address2" class="input-label">@lang('texts.address2')</label>
                            <input id="address2" class="input w-full" name="address2" />
                            @error('address2')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="city" class="input-label">@lang('texts.city')</label>
                            <input id="city" class="input w-full" name="city" />
                            @error('city')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="state" class="input-label">@lang('texts.state')</label>
                            <input id="state" class="input w-full" name="state" />
                            @error('state')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="postal_code" class="input-label">@lang('texts.postal_code')</label>
                            <input id="postal_code" class="input w-full" name="postal_code" />
                            @error('postal_code')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="country" class="input-label">@lang('texts.country')</label>
                            <select id="country" class="input w-full form-select" name="country">
                                @foreach(App\Utils\TranslationHelper::getCountries() as $country)
                                <option value="{{ $country->id }}">
                                    {{ $country->iso_3166_2 }} ({{ $country->name }})
                                </option>
                                @endforeach
                            </select>
                            @error('country')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Client shipping address -->
            <h3 class="text-lg font-medium leading-6 text-gray-900 mt-8">{{ ctrans('texts.shipping_address') }}</h3>
            <p class="mt-1 text-sm leading-5 text-gray-500">
                {{ ctrans('texts.enter_your_shipping_address') }}
            </p>
            <div class="shadow overflow-hidden rounded mt-4">
                <div class="px-4 py-5 bg-white sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-4">
                            <label for="shipping_address1" class="input-label">@lang('texts.shipping_address1')</label>
                            <input id="shipping_address1" class="input w-full" name="shipping_address1" />
                            @error('shipping_address1')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="shipping_address2" class="input-label">@lang('texts.shipping_address2')</label>
                            <input id="shipping_address2" class="input w-full" name="shipping_address2" />
                            @error('shipping_address2')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="shipping_city" class="input-label">@lang('texts.shipping_city')</label>
                            <input id="shipping_city" class="input w-full" name="shipping_city" />
                            @error('shipping_city')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="shipping_state" class="input-label">@lang('texts.shipping_state')</label>
                            <input id="shipping_state" class="input w-full" name="shipping_state" />
                            @error('shipping_state')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="shipping_postal_code" class="input-label">@lang('texts.shipping_postal_code')</label>
                            <input id="shipping_postal_code" class="input w-full" name="shipping_postal_code" />
                            @error('shipping_postal_code')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <label for="shipping_country" class="input-label">@lang('texts.shipping_country')</label>
                            <select id="shipping_country" class="input w-full form-select" name="shipping_country">
                                @foreach(App\Utils\TranslationHelper::getCountries() as $country)
                                <option {{ $country == isset(auth()->user()->client->shipping_country->id) ? 'selected' : null }} value="{{ $country->id }}">
                                    {{ $country->iso_3166_2 }}
                                    ({{ $country->name }})
                                </option>
                                @endforeach
                            </select>
                            @error('country')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center mt-8">
                <span class="inline-flex items-center">
                    <input type="checkbox" name="terms" class="form-checkbox mr-2 cursor-pointer" checked>
                    <span class="text-sm text-gray-800">
                        {{ ctrans('texts.i_agree') }} <a class="button-link" href="https://www.invoiceninja.com/self-hosting-terms-service/">{{ ctrans('texts.terms_of_service') }}</a> and <a class="button-link" href="https://www.invoiceninja.com/self-hosting-privacy-data-control/">{{ ctrans('texts.privacy_policy') }}</a>
                    </span>
                </span>
                <button class="button button-primary">
                    @lang('texts.save')
                </button>
            </div>
        </form>
    </div>
</div>
@endsection