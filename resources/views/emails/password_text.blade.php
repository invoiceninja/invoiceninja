{{ trans('texts.reset_password') }}

{!! URL::to(SITE_URL . "/password/reset/{$token}") !!}

{{ trans('texts.email_signature') }}
{{ trans('texts.email_from') }}

{{ trans('texts.reset_password_footer', ['email' => env('CONTACT_EMAIL', CONTACT_EMAIL)]) }}
