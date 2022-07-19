@extends('portal.ninja2020.layout.vendor_app')

@section('meta_title', ctrans('texts.vendor_information'))

@section('header')
    <p class="leading-5 text-gray-500">{{ ctrans('texts.update_your_personal_info') }}</p>
@endsection

@section('body')
    @if(session()->has('missing_required_fields'))
        <div class="validation validation-fail">
            <p class="mb-3 font-semibold">{{ ctrans('texts.before_proceeding_with_payment_warning') }}:</p>

            <ul>
                @foreach(session()->get('missing_required_fields') as $field)
                    <li class="block">— {{ ctrans("texts.{$field}") }}</li>
                @endforeach
            </ul>

            <button onclick="window.history.back();" class="block mt-3 button button-link pl-0 ml-0 underline">{{ ctrans('texts.after_completing_go_back_to_previous_page') }}</button>
        </div>
    @endif

    <div class="mt-2 sm:mt-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.contact_details') }}</h3>
                </div>
            </div>  <!-- End of left-side -->

            <div class="mt-5 md:mt-0 md:col-span-2">
            	<form action="{{ route('vendor.profile.update', ['vendor_contact' => $contact->hashed_id]) }}" method="post" id="saveVendor">
                    @csrf
                    @method('PUT')
                    <div class="shadow overflow-hidden rounded">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="first_name" class="input-label">@lang('texts.first_name')</label>
                                    <input id="contact_first_name"
                                           class="input w-full {{ in_array('contact_first_name', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}"
                                           name="first_name" value="{{ $contact->first_name }}"/>
                                    @error('first_name')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="last_name" class="input-label">@lang('texts.last_name')</label>
                                    <input id="contact_last_name"
                                           class="input w-full {{ in_array('contact_last_name', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}"
                                           name="last_name" value="{{ $contact->last_name}}"/>
                                    @error('last_name')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="email_address" class="input-label">@lang('texts.email_address')</label>
                                    <input id="contact_email_address"
                                           class="input w-full {{ in_array('contact_email', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}"
                                           type="email" name="email" value="{{ $contact->email }}"/>
                                    @error('email')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="contact_phone" class="input-label">@lang('texts.phone')</label>
                                    <input id="contact_phone" class="input w-full" name="phone"
                                           value="{{ $contact->phone}}"/>
                                    @error('phone')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                            </div>
                        </div>

                    </div>
            </div> <!-- End of main form -->
        </div>
    </div>

<div class="mt-10 sm:mt-6">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1">
            <div class="sm:px-0">
                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.billing_address') }}</h3>
            </div>
        </div>
        <div class="mt-5 md:mt-0 md:col-span-2">
                <div class="px-4 py-5 bg-white sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-4">
                            <label for="address1" class="input-label">{{ ctrans('texts.address1') }}</label>
                            <input id="address1" class="input w-full {{ in_array('billing_address1', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="address1" value="{{ $vendor->address1 }}" />
                            @error('address1')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="address2" class="input-label">{{ ctrans('texts.address2') }}</label>
                            <input id="address2" class="input w-full {{ in_array('billing_address2', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="address2" value="{{ $vendor->address2 }}" />
                            @error('address2')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="city" class="input-label">{{ ctrans('texts.city') }}</label>
                            <input id="city" class="input w-full {{ in_array('billing_city', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="city" value="{{ $vendor->city }}" />
                            @error('city')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="state" class="input-label">{{ ctrans('texts.state') }}</label>
                            <input id="state" class="input w-full {{ in_array('billing_state', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="state" value="{{ $vendor->state }}" />
                            @error('state')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="postal_code" class="input-label">{{ ctrans('texts.postal_code') }}</label>
                            <input id="postal_code" class="input w-full {{ in_array('billing_postal_code', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="postal_code" value="{{ $vendor->postal_code }}" />
                            @error('postal_code')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="country" class="input-label">@lang('texts.country')</label>
                            <select id="country" class="input w-full bg-white form-select {{ in_array('billing_country', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" value="{{ $vendor->country_id }}" name="country_id">
                                <option value="none"></option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" @if($vendor->country_id == $country->id) selected @endif>
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
                         <div class="col-span-6 sm:col-span-6">
                            <label for="public_notes" class="input-label w-full">{{ ctrans('texts.notes') }}</label>
                            <textarea rows="4" id="public_notes" class="block p-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" name="public_notes" value="{{ $vendor->public_notes }}" />{{ $vendor->public_notes}}</textarea>
                            @error('public_notes')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                    <button type="submit" class="button button-primary bg-primary">{{ ctrans('texts.save') }}</button>
                </div>
        </div>
        </form>
    </div>
</div>


@endsection
