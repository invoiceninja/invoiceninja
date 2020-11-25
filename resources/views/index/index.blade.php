<!DOCTYPE html>
<html data-report-errors="{{ $report_errors }}">
<head>
    <!-- Source: https://github.com/invoiceninja/invoiceninja -->
    <!-- Version: {{ config('ninja.app_version') }} -->
  <meta charset="UTF-8">
  <title>Invoice Ninja</title>
  <meta name="google-signin-client_id" content="{{ config('services.google.client_id') }}">
  <link rel="manifest" href="manifest.json?v={{ config('ninja.app_version') }}">
</head>
<body style="background-color:#888888;">

  <style>

    /* fix for blurry fonts 
    flt-glass-pane {
        image-rendering: pixelated;
    }
    */
   
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
        navigator.serviceWorker.register('/flutter_service_worker.js?v={{ config('ninja.app_version') }}');
      });
    }

    document.addEventListener('DOMContentLoaded', function(event) {
      document.getElementById('loader').style.display = 'none';
    });

    /*
    function invokeServiceWorkerUpdateFlow() {
      // you have a better UI here, reloading is not a great user experince here.
      const confirmed = confirm('New version of the app is available. Refresh now');
      if (confirmed) {
        window.location.reload();
      }
    }
    async function handleServiceWorker() {
      if ('serviceWorker' in navigator) {
        // get the ServiceWorkerRegistration instance
        const registration = await navigator.serviceWorker.getRegistration();
        // (it is also returned from navigator.serviceWorker.register() function)

        if (registration) {
          // detect Service Worker update available and wait for it to become installed
          registration.addEventListener('updatefound', () => {
            if (registration.installing) {
              // wait until the new Service worker is actually installed (ready to take over)
              registration.installing.addEventListener('statechange', () => {
                if (registration.waiting) {
                  // if there's an existing controller (previous Service Worker), show the prompt
                  if (navigator.serviceWorker.controller) {
                    invokeServiceWorkerUpdateFlow(registration);
                  } else {
                    // otherwise it's the first install, nothing to do
                    console.log('Service Worker initialized for the first time');
                  }
                }
              });
            }
          });

          let refreshing = false;

          // detect controller change and refresh the page
          navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!refreshing) {
              window.location.reload();
              refreshing = true;
            }
          });
        }
      }
    }

    handleServiceWorker();
  */
  </script>

  <script defer src="main.dart.js?v={{ config('ninja.app_version') }}" type="application/javascript"></script>

  <center style="padding-top: 150px" id="loader">
    <div class="loader"></div>
  </center>

</body>
</html>