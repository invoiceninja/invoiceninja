{!! $title !!}

@isset($body)
{!! plain_email_text($body) !!}
@endisset

@isset($content)
{!! plain_email_text($content) !!}
@endisset

@isset($whitelabel)
@if(!$whitelabel)
{!! ctrans('texts.ninja_email_footer', ['site' => 'https://invoiceninja.com']) !!}
@endif
@endisset