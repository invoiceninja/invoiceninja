<div class="mt-8 text-center break-words">
    <p class="block text-center text-sm break-words">{{ $slot }}</p>

    @isset($url)
        <a href="{{ $url }}" class="text-blue-500 hover:text-blue-600 mt-4 text-sm break-words">
            @isset($url_text)
                {!! $url_text !!}
            @else
                {{ $url }}
            @endisset
        </a>
    @endisset
</div>