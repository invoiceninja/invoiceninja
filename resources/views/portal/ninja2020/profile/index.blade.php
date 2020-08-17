@extends('portal.ninja2020.layout.app')

@section('meta_title', ctrans('texts.client_information'))

@section('header')
    <p class="leading-5 text-gray-500">{{ ctrans('texts.update_your_personal_info') }}</p>
@endsection

@section('body')

    <!-- Basic information: first & last name, e-mail address etc. -->
    <div class="mt-2 sm:mt-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.profile') }}</h3>
                    <p class="mt-1 text-sm leading-5 text-gray-500">
                        @lang('texts.client_information_text')
                    </p>
                </div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form action="{{ route('client.profile.update', auth()->user()->hashed_id) }}" method="POST"
                      id="update_contact">
                    @csrf
                    @method('PUT')
                    <div class="shadow overflow-hidden rounded">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="first_name" class="input-label">@lang('texts.first_name')</label>
                                    <input id="first_name" class="input w-full" name="first_name"
                                           value="{{ auth()->user()->first_name }}"/>
                                    @error('first_name')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="last_name" class="input-label">@lang('texts.last_name')</label>
                                    <input id="last_name" class="input w-full" name="last_name"
                                           value="{{ auth()->user()->last_name }}"/>
                                    @error('last_name')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="email_address" class="input-label">@lang('texts.email_address')</label>
                                    <input id="email_address" class="input w-full" type="email" name="email"
                                           value="{{ auth()->user()->email }}"/>
                                    @error('email')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="phone" class="input-label">@lang('texts.phone')</label>
                                    <input id="phone" class="input w-full" name="phone" value="{{ auth()->user()->phone }}"/>
                                    @error('phone')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-6 lg:col-span-3">
                                    <label for="password" class="input-label">@lang('texts.password')</label>
                                    <input id="password" class="input w-full" name="password" type="password"/>
                                    @error('password')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-3 lg:col-span-3">
                                    <label for="state" class="input-label">@lang('texts.confirm_password')</label>
                                    <input id="state" class="input w-full" name="password_confirmation" type="password"/>
                                    @error('password_confirmation')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button class="button button-primary">
                                @lang('texts.save')
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Name, website & logo -->
    <div class="mt-10 sm:mt-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.name_website_logo') }}</h3>
                    <p class="mt-1 text-sm leading-5 text-gray-500">
                       {{ ctrans('texts.make_sure_use_full_link') }}
                    </p>
                </div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form action="{{ route('client.profile.edit_client', auth()->user()->hashed_id) }}" method="POST"
                      id="update_contact">
                    @csrf
                    @method('PUT')
                    <div class="shadow overflow-hidden rounded">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="street" class="input-label">@lang('texts.name')</label>
                                    <input id="name" class="input w-full" name="name"
                                           value="{{ auth()->user()->client->present()->name }}"/>
                                    @error('name')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="website" class="input-label">@lang('texts.website')</label>
                                    <input id="website" class="input w-full" name="website"
                                           value="{{ auth()->user()->client->website }}"/>
                                    @error('website')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                            </div>
                        </div>
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button class="button button-primary">
                                @lang('texts.save')
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Client personal address -->
    <div class="mt-10 sm:mt-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.personal_address') }}</h3>
                    <p class="mt-1 text-sm leading-5 text-gray-500">
                        {{ ctrans('texts.enter_your_personal_address') }}
                    </p>
                </div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form action="{{ route('client.profile.edit_client', auth()->user()->hashed_id) }}" method="POST"
                      id="update_contact">
                    @csrf
                    @method('PUT')
                    <div class="shadow overflow-hidden rounded">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-4">
                                    <label for="address1" class="input-label">@lang('texts.address1')</label>
                                    <input id="address1" class="input w-full" name="address1"
                                           value="{{ auth()->user()->client->address1 }}"/>
                                    @error('address1')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="address2" class="input-label">@lang('texts.address2')</label>
                                    <input id="address2" class="input w-full" name="address2"
                                           value="{{ auth()->user()->client->address2 }}"/>
                                    @error('address2')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="city" class="input-label">@lang('texts.city')</label>
                                    <input id="city" class="input w-full" name="city"
                                           value="{{ auth()->user()->client->city }}"/>
                                    @error('city')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label for="state" class="input-label">@lang('texts.state')</label>
                                    <input id="state" class="input w-full" name="state"
                                           value="{{ auth()->user()->client->state }}"/>
                                    @error('state')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label for="postal_code" class="input-label">@lang('texts.postal_code')</label>
                                    <input id="postal_code" class="input w-full" name="postal_code"
                                           value="{{ auth()->user()->client->postal_code }}"/>
                                    @error('postal_code')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label for="country" class="input-label">@lang('texts.country')</label>
                                    <select id="country" class="input w-full form-select" name="country_id">
                                        @foreach($countries as $country)
                                            <option
                                                {{ $country->id == auth()->user()->client->country_id ? 'selected' : null }} value="{{ $country->id }}">
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
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button class="button button-primary">
                                @lang('texts.save')
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Client shipping address -->
    <div class="mt-10 sm:mt-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.shipping_address') }}</h3>
                    <p class="mt-1 text-sm leading-5 text-gray-500">
                        {{ ctrans('texts.enter_your_shipping_address') }}
                    </p>
                </div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form action="{{ route('client.profile.edit_client', auth()->user()->hashed_id) }}" method="POST"
                      id="update_contact">
                    @csrf
                    @method('PUT')
                    <div class="shadow overflow-hidden rounded">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-4">
                                    <label for="shipping_address1"
                                           class="input-label">@lang('texts.shipping_address1')</label>
                                    <input id="shipping_address1" class="input w-full" name="shipping_address1"
                                           value="{{ auth()->user()->client->shipping_address1 }}"/>
                                    @error('shipping_address1')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="shipping_address2"
                                           class="input-label">@lang('texts.shipping_address2')</label>
                                    <input id="shipping_address2" class="input w-full" name="shipping_address2"
                                           value="{{ auth()->user()->client->shipping_address2 }}"/>
                                    @error('shipping_address2')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="shipping_city" class="input-label">@lang('texts.shipping_city')</label>
                                    <input id="shipping_city" class="input w-full" name="shipping_city"
                                           value="{{ auth()->user()->client->shipping_city }}"/>
                                    @error('shipping_city')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label for="shipping_state"
                                           class="input-label">@lang('texts.shipping_state')</label>
                                    <input id="shipping_state" class="input w-full" name="shipping_state"
                                           value="{{ auth()->user()->client->shipping_state }}"/>
                                    @error('shipping_state')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label for="shipping_postal_code"
                                           class="input-label">@lang('texts.shipping_postal_code')</label>
                                    <input id="shipping_postal_code" class="input w-full" name="shipping_postal_code"
                                           value="{{ auth()->user()->client->shipping_postal_code }}"/>
                                    @error('shipping_postal_code')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="col-span-4 sm:col-span-2">
                                    <label for="shipping_country"
                                           class="input-label">@lang('texts.shipping_country')</label>
                                    <select id="shipping_country" class="input w-full form-select" name="shipping_country_id">
                                        @foreach($countries as $country)
                                            <option
                                                {{ $country->id == auth()->user()->client->shipping_country_id ? 'selected' : null }} value="{{ $country->id }}">
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
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button class="button button-primary">
                                @lang('texts.save')
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
