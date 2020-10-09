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
            <form wire:submit.prevent="submit" method="POST" id="update_contact">
                @csrf
                <div class="px-4 py-5 bg-white sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-4">
                            <label for="address1" class="input-label">{{ ctrans('texts.address1') }}</label>
                            <input id="address1" class="input w-full" name="address1" wire:model.defer="address1" />
                            @error('address1')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="address2" class="input-label">{{ ctrans('texts.address2') }}</label>
                            <input id="address2" class="input w-full" name="address2" wire:model.defer="address2" />
                            @error('address2')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label for="city" class="input-label">{{ ctrans('texts.city') }}</label>
                            <input id="city" class="input w-full" name="city" wire:model.defer="city" />
                            @error('city')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="state" class="input-label">{{ ctrans('texts.state') }}</label>
                            <input id="state" class="input w-full" name="state" wire:model.defer="state" />
                            @error('state')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="postal_code" class="input-label">{{ ctrans('texts.postal_code') }}</label>
                            <input id="postal_code" class="input w-full" name="postal_code" wire:model.defer="postal_code" />
                            @error('postal_code')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="col-span-6 sm:col-span-2">
                            <label for="country" class="input-label">@lang('texts.country')</label>
                            <select id="country" class="input w-full form-select" wire:model.defer="country_id">
                                <option value="none"></option>
                                @foreach($countries as $country)
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
                <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                    <button class="button button-primary bg-primary">{{ $saved }}</button>
                </div>
        </div>
        </form>
    </div>
</div>
