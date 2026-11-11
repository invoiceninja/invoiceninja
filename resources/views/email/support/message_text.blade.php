{!! plain_email_text($support_message_plain ?? $support_message) !!}

@if(! empty($system_info))
---
{!! plain_email_text($system_info) !!}
@endif

@if(! empty($laravel_log))
{!! ctrans('texts.display_log') !!}
@foreach($laravel_log as $log_line)
{!! $log_line !!}
@endforeach
@endif
