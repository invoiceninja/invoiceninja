<div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
        {{ ctrans('texts.transit_number') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="transit_number" max="5" name="transit_number" type="text" placeholder="{{ ctrans('texts.transit_number') }}" required value="{{ old('transit_number', $transit_number) }}">
     @error('transit_number')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </dd>
</div>

<div class="px-4 py-2 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
        {{ ctrans('texts.institution_number') }}
    </dt>
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        <input class="input w-full" id="institution_number" max="3" name="institution_number" type="text" placeholder="{{ ctrans('texts.institution_number') }}" required value="{{ old('institution_number', $institution_number) }}">
        @error('institution_number')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
        @enderror
    </dd>
</div>
