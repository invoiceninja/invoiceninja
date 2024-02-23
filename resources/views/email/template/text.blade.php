{{ strip_tags(str_replace("<br>", "\r\n", $text_body)) }}

@isset($whitelabel)
@if(!$whitelabel)
{{ ctrans('texts.ninja_email_footer', ['site' => 'https://invoiceninja.com']) }}
@endif
@endisset
