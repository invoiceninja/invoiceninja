@extends('portal.ninja2020.layout.clean')

@push('head')
    <meta name="pdf-url" content="{{ asset($entity->pdf_file_path(null, 'url',true)) }}">
    <script src="{{ asset('js/vendor/pdf.js/pdf.min.js') }}"></script>
    <script src="{{ asset('vendor/alpinejs@2.8.2/alpine.js') }}" defer></script>
@endpush

@section('body')
    <div class="container mx-auto my-10">
        <div class="flex items-center justify-between">
            <section class="flex items-center">
                <button class="input-label" id="previous-page-button">
                    <svg class="w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button class="input-label" id="next-page-button">
                    <svg class="w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </section>
            <div class="flex items-center">
                @if($entity instanceof App\Models\Invoice)
                    <button class="button button-primary bg-blue-600">{{ ctrans('texts.pay_now') }}</button>
                @elseif($$entity instanceof App\Models\Quote)
                    <button class="button button-primary bg-blue-600">{{ ctrans('texts.approve') }}</button>
                @endif
                <button class="button button-primary bg-blue-600 ml-2">{{ ctrans('texts.download') }}</button>
                <div x-data="{ open: false }" @keydown.escape="open = false" @click.away="open = false" class="relative inline-block text-left ml-2">
                    <div>
                        <button @click="open = !open" class="flex items-center text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg">
                        <div class="rounded-md bg-white ring-1 ring-black ring-opacity-5">
                            <div class="py-1">
                                <a target="_blank" href="{{ asset($entity->pdf_file_path(null, 'url',true)) }}" class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900">{{ ctrans('texts.open_in_new_tab') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-center">
            <canvas id="pdf-placeholder" class="shadow-lg border rounded-lg bg-white mt-4 p-4"></canvas>
        </div>
    </div>
@endsection

@section('footer')
    <script src="{{ asset('js/clients/shared/pdf.js') }}"></script>
@endsection