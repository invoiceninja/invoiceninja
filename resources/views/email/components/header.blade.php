<div class="h-64 flex flex-col items-center justify-center text-center tracking-wide leading-normal bg-gray-900 -mx-8 -mt-8 p-4">
    <h1 class="text-white text-4xl font-semibold">{{ $slot }}</h1>
    <p class="text-white text-xl">
        @isset($p)
            {{ $p }}
        @endisset   
    </p>
</div>