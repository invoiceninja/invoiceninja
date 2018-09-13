{!! strip_tags(str_replace('</div>', "\n\n", $body)) !!}

@if (! $account->isPaid())
    {{ trans('texts.ninja_email_footer', ['site' => NINJA_WEB_URL . '?utm_source=email_footer']) }}
@endif
