@component('mail::layout')

{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
Header Title
@endcomponent
@endslot

{{-- Body --}}
{{ $message }}

{!! str_replace('\n', '<br>', $system_info) !!}

@if(@count($laravel_log) > 0)
<details>
    <summary>{{ ctrans('texts.display_log') }}</summary>
    @foreach($laravel_log as $log_line)
        <small>{{ $log_line }}</small> <br>
    @endforeach
</details>
@endif
{{-- Subcopy --}}
@isset($subcopy)
@slot('subcopy')
@component('mail::subcopy')
{{ $subcopy }}
@endcomponent
@endslot
@endisset


{{-- Footer --}}
@slot('footer')
@component('mail::footer')
© {{ date('Y') }} {{ config('ninja.app_name') }}.
@endcomponent
@endslot

@endcomponent
