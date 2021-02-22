<!-- Name, website -->
<h3 class="text-lg font-medium leading-6 text-gray-900 mt-8">{{ ctrans('texts.website') }}</h3>

<p class="mt-1 text-sm leading-5 text-gray-500">
    {{ ctrans('texts.make_sure_use_full_link') }}
</p>

<div class="shadow overflow-hidden rounded mt-4">
    <div class="px-4 py-5 bg-white sm:p-6">
        <div class="grid grid-cols-6 gap-6">
            <div class="col-span-6 sm:col-span-3">
                <label for="street" class="input-label">{{ ctrans('texts.name') }}</label>
                <input id="name" class="input w-full" name="name" />
                @error('name')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="col-span-6 sm:col-span-3">
                <label for="website" class="input-label">{{ ctrans('texts.website') }}</label>
                <input id="website" class="input w-full" name="website" />
                @error('website')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        </div>
    </div>
</div>
