<div class="mt-10 sm:mt-6">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1">
            <div class="sm:px-0">
                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ ctrans('texts.name_website_logo') }}</h3>
                <p class="mt-1 text-sm leading-5 text-gray-500">
                    {{ ctrans('texts.make_sure_use_full_link') }}
                </p>
            </div>
        </div> <!-- End of left side -->

        <div class="mt-5 md:mt-0 md:col-span-2">
            <form wire:submit.prevent="submit" method="POST" id="update_contact">
                @csrf
                @method('PUT')
                <div class="shadow overflow-hidden rounded">
                    <div class="px-4 py-5 bg-white sm:p-6">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <label for="street" class="input-label">{{ ctrans('texts.name') }}</label>
                                <input id="name" class="input w-full" name="name" wire:model.defer="name" />
                                @error('name')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="col-span-6 sm:col-span-3">
                                <label for="website" class="input-label">{{ ctrans('texts.website') }}</label>
                                <input id="website" class="input w-full" name="website" wire:model.defer="website" />
                                @error('website')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                        </div>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                        <button class="button button-primary">{{ $saved }}</button>
                    </div>
                </div>
            </form>
        </div> <!-- End of right side -->
    </div>
</div>
