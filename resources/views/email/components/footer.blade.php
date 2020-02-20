<div class="mt-8 text-center">
    <p class="text-center sm:text-lg">{{ $slot }}</p>

    @isset($url)
        <a href="{{ $url }}" class="text-blue-500 hover:text-blue-600">
            @isset($url_text)
                {!! $url_text !!}
            @else
                {{ $url }}
            @endisset
        </a>
    @endisset
</div>