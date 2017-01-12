<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>
    @if ($account->emailMarkupEnabled())
        @include('emails.partials.client_view_action')
    @endif
    {!! $body !!}
</body>
</html>