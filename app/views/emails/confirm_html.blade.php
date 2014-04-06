<h1>{{ trans('texts.confirmation_header') }}</h1>

{{ trans('texts.confirmation_message') }}<p/>
<a href='{{{ URL::to("user/confirm/{$user->confirmation_code}") }}}'>
    {{{ URL::to("user/confirm/{$user->confirmation_code}") }}}
</a><p/>

{{ trans('texts.email_signature') }}<br/>
{{ trans('texts.email_from') }}