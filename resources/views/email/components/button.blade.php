<div class="px-10 py-6 flex flex-col items-center">
    <a href="{{ $url }}" class="bg-green-500 px-4 py-3 text-white leading-tight hover:bg-green-600" style="color: white !important;">{{ $slot }}</a>
</div>

@isset($show_link)
<div class="flex flex-col">
    <p>{{ ctrans('texts.email_link_not_working') }}:</p>
    <a class="text-green-700 hover:text-green-800" href="{{ $url }}">{{ $url }}</a>
</div>
@endisset