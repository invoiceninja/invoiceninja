<!DOCTYPE html>
<html data-report-errors="{{ $report_errors }}" data-rc="{{ $rc }}" data-user-agent="{{ $user_agent }}" data-login="{{ $login }}">
<head>
    <!-- Source: https://github.com/invoiceninja/invoiceninja -->
    <!-- Version: {{ config('ninja.app_version') }} -->
  <meta charset="UTF-8">
  <title>{{ config('ninja.app_name') }}</title>
  <meta name="google-signin-client_id" content="{{ config('services.google.client_id') }}">

  @include('react.head')

</head>

<body class="h-full">
  <noscript>You need to enable JavaScript to run this app.</noscript>
  <div id="root"></div>
  
</body>

<!--

If you are reading this, there is a fair change that the react application has not loaded for you. There are a couple of solutions:

1. Download the release file from https://github.com/invoiceninja/invoiceninja and overwrite your current installation.
2. Switch back to the Flutter application by editing the database, you can do this with the following SQL

UPDATE accounts SET
set_react_as_default_ap = 0;

-->
</html>
