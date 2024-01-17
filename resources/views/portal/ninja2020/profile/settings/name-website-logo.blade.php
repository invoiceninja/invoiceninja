<div class="mt-10 sm:mt-6">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1">
            <div class="sm:px-0">
                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.client_details') }}</h3>
            </div>
        </div> <!-- End of left side -->

        <div class="mt-5 md:mt-0 md:col-span-2">
            <form wire:submit="submit" method="POST" id="update_contact">
                @csrf
                <div class="shadow overflow-hidden rounded">
                    <div class="px-4 py-5 bg-white sm:p-6">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <label for="client_name" class="input-label">{{ ctrans('texts.name') }}</label>
                                <input id="client_name" class="input w-full" name="name" wire:model="name"/>
                                @error('name')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="client_vat_number"
                                       class="input-label">{{ ctrans('texts.vat_number') }}</label>
                                <input id="client_vat_number" class="input w-full" name="vat_number"
                                       wire:model="vat_number"/>
                                @error('vat_number')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="client_phone" class="input-label">{{ ctrans('texts.phone') }}</label>
                                <input id="client_phone" class="input w-full" name="phone" wire:model="phone"/>
                                @error('phone')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <div class="inline-flex items-center">
                                    <label for="client_website"
                                           class="input-label">{{ ctrans('texts.website') }}</label>
                                    <span class="text-xs ml-2 text-gray-600">E.g. https://example.com</span>
                                </div>
                                <input id="client_website" class="input w-full" name="website"
                                       wire:model="website"/>
                                @error('website')
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
        </div> <!-- End of right side -->
    </div>
</div>
