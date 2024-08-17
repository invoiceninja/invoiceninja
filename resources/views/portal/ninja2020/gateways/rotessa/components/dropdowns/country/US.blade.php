<div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
            {{ ctrans('texts.state') }}
        </dt>
        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            <select class="input w-full" id="province_code" required name="province_code">
                @foreach($states as $code => $state)
                    <option value="{{ $code }}"  @selected(old('province_code', $province_code) == $code ) >{{ $state }}</option>
                @endforeach
            </select>
            @error('province_code')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </dd>
    </div>
