<div class="mt-10 sm:mt-6">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1">
            <div class="sm:px-0">
                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.shipping_address') }}</h3>
            </div>
        </div>
        <div class="mt-5 md:mt-0 md:col-span-2">
            <form wire:submit="submit" method="POST" id="update_shipping_address">
                @csrf
                <div class="shadow overflow-hidden rounded">
                    <div class="px-4 py-5 bg-white sm:p-6">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-4">
                                <label for="shipping_address1" class="input-label">{{ ctrans('texts.shipping_address1') }}</label>
                                <input id="shipping_address1" class="input w-full" name="shipping_address1" wire:model="shipping_address1" />
                                @error('shipping_address1')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="col-span-6 sm:col-span-3">
                                <label for="shipping_address2" class="input-label">@lang('texts.shipping_address2')</label>
                                <input id="shipping_address2" class="input w-full" name="shipping_address2" wire:model="shipping_address2" />
                                @error('shipping_address2')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="col-span-6 sm:col-span-3">
                                <label for="shipping_city" class="input-label">@lang('texts.shipping_city')</label>
                                <input id="shipping_city" class="input w-full" name="shipping_city" wire:model="shipping_city" />
                                @error('shipping_city')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="col-span-6 sm:col-span-2">
                                <label for="shipping_state" class="input-label">@lang('texts.shipping_state')</label>
                                <input id="shipping_state" class="input w-full" name="shipping_state" wire:model="shipping_state" />
                                @error('shipping_state')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="col-span-6 sm:col-span-2">
                                <label for="shipping_postal_code" class="input-label">@lang('texts.shipping_postal_code')</label>
                                <input id="shipping_postal_code" class="input w-full" name="shipping_postal_code" wire:model="shipping_postal_code" />
                                @error('shipping_postal_code')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="col-span-4 sm:col-span-2">
                                <label for="shipping_country" class="input-label">@lang('texts.shipping_country')</label>
                                <select id="shipping_country" class="input w-full form-select bg-white" wire:model="shipping_country_id">
                                    <option value="none"></option>
                                    @foreach($countries as $country)
                                    <option value="{{ $country->id }}">
                                        {{ $country->iso_3166_2 }} ({{ $country->getName() }})
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
                        <button class="button button-primary bg-primary">
                            {{ $saved }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
