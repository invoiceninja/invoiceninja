<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>
    @if ($account->enable_email_markup)
        @include('emails.view_action', ['link' => $link, 'entityType' => $entityType])
    @endif
    {!! $body !!}
</body>
</html>