{{ trans('texts.reset_password') }}

{!! URL::to(SITE_URL . "/client/password/reset/{$token}") !!}

@if (Utils::isNinja() || ! Utils::isWhiteLabel())
    {{ trans('texts.email_signature') }}<br/>
    {{ trans('texts.email_from') }}
@endif

{{ trans('texts.reset_password_footer', ['email' => env('CONTACT_EMAIL', CONTACT_EMAIL)]) }}
