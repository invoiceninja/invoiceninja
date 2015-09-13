<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>
@if (false && !$invitationMessage)
    @include('emails.confirm_action', ['user' => $user])
@endif

<h1>{{ trans('texts.confirmation_header') }}</h1>

<p>
    {{ $invitationMessage . trans('texts.confirmation_message') }}<br/>
    <a href='{!! URL::to("user/confirm/{$user->confirmation_code}") !!}'>
        {!! URL::to("user/confirm/{$user->confirmation_code}")!!}
    </a>
    <p/>

    {{ trans('texts.email_signature') }}<br/>
    {{ trans('texts.email_from') }}
</p>

</body>
</html>