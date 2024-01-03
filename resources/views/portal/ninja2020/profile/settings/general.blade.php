    <div class="mt-2 sm:mt-6">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.contact_details') }}</h3>
                </div>
            </div>  <!-- End of left-side -->

            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="submit" id="update_client">
                    @csrf
                    @method('PUT')
                    <div class="shadow overflow-hidden rounded">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="first_name" class="input-label">@lang('texts.first_name')</label>
                                    <input id="contact_first_name"
                                           class="input w-full {{ in_array('contact_first_name', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}"
                                           name="first_name" wire:model="first_name"/>
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
                                           name="last_name" wire:model="last_name"/>
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
                                           type="email" name="email" wire:model="email"/>
                                    @error('email')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="contact_phone" class="input-label">@lang('texts.phone')</label>
                                    <input id="contact_phone" class="input w-full" name="phone"
                                           wire:model="phone"/>
                                    @error('phone')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-6 lg:col-span-3">
                                    <label for="contact_password" class="input-label">@lang('texts.password')</label>
                                    <input id="contact_password" class="input w-full" name="password"
                                           wire:model="password" type="password"/>
                                    @error('password')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>

                                <div class="col-span-6 sm:col-span-3 lg:col-span-3">
                                    <label for="contact_password_confirmation"
                                           class="input-label">@lang('texts.confirm_password')</label>
                                    <input id="contact_password_confirmation" class="input w-full"
                                           name="password_confirmation"
                                           wire:model="password_confirmation" type="password"/>
                                    @error('password_confirmation')
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button data-ref="update-contact-details"
                                    class="button button-primary bg-primary">{{ $saved }}</button>
                        </div>
                    </div>
                </form>
            </div> <!-- End of main form -->
        </div>
    </div>
