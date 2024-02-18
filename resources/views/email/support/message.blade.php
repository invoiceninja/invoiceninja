@component('email.template.admin', ['settings' => $settings, 'logo' => $logo ?? 'https://pdf.invoicing.co/favicon-v2.png'])
    {{-- Body --}}
    {!! $support_message !!}

<hr>

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
