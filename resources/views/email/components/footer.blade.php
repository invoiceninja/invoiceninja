<p>{{ $slot }}</p>

@isset($url)
    <a href="{{ $url }}" target="_blank">
        @isset($url_text)
            {!! $url_text !!}
        @else
            {{ $url }}
        @endisset
    </a>
@endisset
