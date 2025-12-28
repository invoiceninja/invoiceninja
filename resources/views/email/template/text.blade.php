{{ strip_tags(str_replace("<br>", "\r\n", $text_body)) }}

@isset($whitelabel)
@if(!$whitelabel)
@endif
@endisset
