{{ trans('texts.confirmation_header') }}

{{ trans('texts.confirmation_message') }}
{{{ URL::to("user/confirm/{$user->confirmation_code}") }}}

{{ trans('texts.email_signature') }}
{{ trans('texts.email_from') }}