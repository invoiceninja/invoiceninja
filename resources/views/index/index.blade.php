<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Invoice Ninja</title>
  <meta name="report_errors" content="{{ $report_errors }}">
  <meta name="google-signin-client_id" content="{{ config('services.google.client_id') }}">
  <meta name="minimum_client_version" content="{{ config('ninja.minimum_client_version') }}">
</head>
<body style="background-color:#888888;">

  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('/flutter_service_worker.js');
      });
    }
  </script>

  <script src="main.dart.js?version={{ config('ninja.app_version') }}" type="application/javascript"></script>

  <center style="font-family:Tahoma,Geneva,sans-serif;font-size:28px;color:white;padding-top:100px">
    Loading...
  </center>

</body>
</html>
