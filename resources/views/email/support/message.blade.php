@component('email.template.master', ['design' => 'light'])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
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
@endcomponent
