@extends('layouts.ninja')
@section('meta_title', ctrans('texts.settings'))

@section('body')
<div class="flex flex-col justify-center items-center mt-10">
    <div class="mb-4">
        <svg height="60" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="56" height="56" rx="8" fill="#006AFF"/>
            <path d="M36.65 14H19.35C16.39 14 14 16.39 14 19.35V36.65C14 39.61 16.39 42 19.35 42H36.65C39.61 42 42 39.61 42 36.65V19.35C42 16.39 39.61 14 36.65 14ZM37 34.2C37 35.75 35.75 37 34.2 37H21.8C20.25 37 19 35.75 19 34.2V21.8C19 20.25 20.25 19 21.8 19H34.2C35.75 19 37 20.25 37 21.8V34.2Z" fill="white"/>
        </svg>
    </div>

    <h2 class="text-xl font-semibold mb-2">Select a Location</h2>
    <p class="text-gray-600 mb-6">Choose which Square location to use for processing payments.</p>

    @if(count($locations) === 0)
        <p class="text-red-600">No locations were found on your Square account. Please create a location in your Square dashboard first.</p>
        <span class="mt-4">Click <a class="font-semibold hover:underline" href="{{ url('/#/settings/company_gateways') }}">here</a> to go back.</span>
    @else
        <form method="POST" action="{{ route('square.oauth.select_location') }}" class="w-full max-w-md">
            @csrf
            <input type="hidden" name="company_key" value="{{ $company_key }}">

            <div class="space-y-3 mb-6">
                @foreach($locations as $location)
                    <label class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors {{ $location['status'] !== 'ACTIVE' ? 'opacity-50' : '' }}">
                        <input
                            type="radio"
                            name="location_id"
                            value="{{ $location['id'] }}"
                            class="mt-1 mr-3"
                            {{ $location['status'] !== 'ACTIVE' ? 'disabled' : '' }}
                            {{ count($locations) === 1 && $location['status'] === 'ACTIVE' ? 'checked' : '' }}
                            required
                        >
                        <div>
                            <div class="font-semibold">{{ $location['name'] }}</div>
                            @if($location['address'])
                                <div class="text-sm text-gray-500">{{ $location['address'] }}</div>
                            @endif
                            @if($location['status'] !== 'ACTIVE')
                                <div class="text-xs text-red-500 mt-1">{{ $location['status'] }}</div>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                Continue
            </button>
        </form>
    @endif
</div>
@endsection
