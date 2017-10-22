<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
  <meta charset="utf-8">
</head>
<body>
    @if ($account->emailMarkupEnabled())
        @include('emails.partials.client_view_action', ['link' => $link])
    @endif
    {!! $body !!}
    @if (! $account->isPaid())
        <br/>
        {!! trans('texts.ninja_email_footer', ['site' => link_to(NINJA_WEB_URL . '?utm_source=email_footer', APP_NAME)]) !!}
    @endif
</body>
</html>
