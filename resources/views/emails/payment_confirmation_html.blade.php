<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>
    @if ($account->enable_email_markup)
        @include('emails.partials.client_view_action', ['link' => $link])
    @endif
    {!! $body !!}
</body>
</html>
