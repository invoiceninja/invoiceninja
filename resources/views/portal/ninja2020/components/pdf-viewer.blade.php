@php
    $mobile = stripos(request()->server('HTTP_USER_AGENT'), 'Android') || stripos(request()->server('HTTP_USER_AGENT'), 'iPhone') || stripos(request()->server('HTTP_USER_AGENT'), 'iPod') || stripos(request()->server('HTTP_USER_AGENT'), 'iPad');
@endphp

@push('head')
    <meta name="pdf-url" content="{{ $url ?? $entity->pdf_file_path($invitation, 'url', true) }}?cache_buster={{time()}}">
    <script src="{{ asset('js/vendor/pdf.js/pdf.min.js') }}"></script>
@endpush

<div class="flex items-center justify-between mt-4">
    <section class="flex items-center">
        <div class="items-center" style="{{ $mobile ? '' : 'display: none' }}" id="pagination-button-container">
            <button class="input-label focus:outline-none hover:text-blue-600 transition ease-in-out duration-300"
                id="previous-page-button" title="Previous page">
                <svg class="w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button class="input-label focus:outline-none hover:text-blue-600 transition ease-in-out duration-300"
                id="next-page-button" title="Next page">
                <svg class="w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
        <span class="text-sm text-gray-700 ml-2 {{ $mobile ? 'block' : 'hidden' }}">{{ ctrans('texts.page') }}:
            <span id="current-page-container"></span>
            <span>{{ strtolower(ctrans('texts.of')) }}</span>
            <span id="total-page-container"></span>
        </span>
    </section>
    <section class="flex items-center space-x-1">
        <div class="flex items-center mr-4 space-x-1 {{ $mobile ? 'block' : 'hidden' }}">
            <span class="text-gray-600 mr-2" id="zoom-level">100%</span>
            <a href="#" id="zoom-in">
                <svg class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 cursor-pointer"
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    <line x1="11" y1="8" x2="11" y2="14"></line>
                    <line x1="8" y1="11" x2="14" y2="11"></line>
                </svg>
            </a>
            <a href="#" id="zoom-out">
                <svg class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 cursor-pointer"
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    <line x1="8" y1="11" x2="14" y2="11"></line>
                </svg>
            </a>
        </div>
        <div x-data="{ open: false }" @keydown.escape="open = false" @click.away="open = false"
            class="relative inline-block text-left">
            <div>
                <button @click="open = !open"
                    class="flex items-center text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                    </svg>
                </button>
            </div>
            <div x-show="open" x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg">
                <div class="rounded-md bg-white ring-1 ring-black ring-opacity-5">
                    <div class="py-1">
                        <a target="_blank" href="{{ $fullscreen_url ?? '?mode=fullscreen' }}"
                            class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900">{{ ctrans('texts.open_in_new_tab') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

@if($mobile)
    <div class="w-full h-full overflow-auto mt-4">
        <canvas id="pdf-placeholder" class="shadow rounded-lg bg-white"></canvas>
    </div>
@else
    <iframe id="pdf-iframe" src="{{ $url ?? $entity->pdf_file_path($invitation, 'url', true) }}?cache_buster={{time()}}" class="h-screen w-full border-0 mt-4"></iframe>
@endif


@if($mobile)
    @push('footer')
        <script src="{{ asset('js/clients/shared/pdf.js') }}" defer></script>
    @endpush
@endif
