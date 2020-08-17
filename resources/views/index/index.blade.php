<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Invoice Ninja</title>
  <meta name="report_errors" content="{{ $report_errors }}">
  <meta name="google-signin-client_id" content="{{ config('services.google.client_id') }}">
  <link rel="manifest" href="manifest.json?v={{ $hash }}">
</head>
<body style="background-color:#888888;">

  <style>

    /* https://projects.lukehaas.me/css-loaders/ */
    .loader,
    .loader:before,
    .loader:after {
      border-radius: 50%;
      width: 2.5em;
      height: 2.5em;
      -webkit-animation-fill-mode: both;
      animation-fill-mode: both;
      -webkit-animation: load7 1.8s infinite ease-in-out;
      animation: load7 1.8s infinite ease-in-out;
    }
    .loader {
      color: #ffffff;
      font-size: 10px;
      margin: 80px auto;
      position: relative;
      text-indent: -9999em;
      -webkit-transform: translateZ(0);
      -ms-transform: translateZ(0);
      transform: translateZ(0);
      -webkit-animation-delay: -0.40s;
      animation-delay: -0.40s;
    }
    .loader:before,
    .loader:after {
      content: '';
      position: absolute;
      top: 0;
    }
    .loader:before {
      left: -3.5em;
      -webkit-animation-delay: -0.80s;
      animation-delay: -0.80s;
    }
    .loader:after {
      left: 3.5em;
    }
    @-webkit-keyframes load7 {
      0%,
      80%,
      100% {
        box-shadow: 0 2.5em 0 -1.3em;
      }
      40% {
        box-shadow: 0 2.5em 0 0;
      }
    }
    @keyframes load7 {
      0%,
      80%,
      100% {
        box-shadow: 0 2.5em 0 -1.3em;
      }
      40% {
        box-shadow: 0 2.5em 0 0;
      }
    }

  </style>

  <script>
    @if (request()->clear)
      window.onload = function() {
        window.localStorage.clear();
      }
    @endif
    
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('/flutter_service_worker.js?v={{ $hash }}');
      });
    }

    document.addEventListener('DOMContentLoaded', function(event) {
      document.getElementById('loader').style.display = 'none';
    });
  </script>

  <script defer src="main.dart.js?v={{ $hash }}" type="application/javascript"></script>

  <center style="padding-top: 150px" id="loader">
    <div class="loader"></div>
  </center>

</body>
</html>