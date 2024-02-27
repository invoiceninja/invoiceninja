{{ $title }}

@isset($body)
{{ strip_tags(str_replace("<br>", "\r\n", $body)) }}
@endisset

@isset($content)
{{ strip_tags(str_replace("<br>", "\r\n", $content)) }}
@endisset

@isset($whitelabel)
@if(!$whitelabel)
{{ ctrans('texts.ninja_email_footer', ['site' => 'https://invoiceninja.com']) }}
@endif
@endisset