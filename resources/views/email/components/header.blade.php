<div id="header" class="border-b {{ isset($logo) ? 'p-6' : '' }} flex justify-center">
    @isset($logo)
        <img src="{{ $logo }}" style="height: 6rem;">
    @endisset
</div>

<div class="flex flex-col items-center mt-8 mb-4">
    <h1 id="title" class="text-2xl md:text-3xl mt-5">
        {{ $slot }}
    </h1>
    @isset($p)
        {!! $p !!}
    @endisset
</div>