{{--
    Where a shared record link lands when the OS did not open the app for it —
    invoiceninja/flutter#144. Only a host the app's manifest names can be
    verified, so every self-hosted domain arrives here and the browser is the
    only thing that can hand the link on. No session, no database, no external
    assets: messengers fetch this URL to build a preview card.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('ninja.app_name') }}</title>

    {{-- Messengers unfurl these links; without this the card is a bare URL. --}}
    <meta property="og:title" content="{{ config('ninja.app_name') }}">
    <meta property="og:description" content="{{ ctrans('texts.open_in_app') }}">
    <meta property="og:image" content="{{ config('ninja.app_logo') }}">
    <meta name="twitter:card" content="summary">

    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f6f4ef;
            color: #111827;
            font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                  Helvetica, Arial, sans-serif;
        }
        main { padding: 24px; max-width: 22rem; width: 100%; text-align: center; }
        img { width: 56px; height: 56px; border-radius: 12px; }
        h1 { font-size: 1.125rem; font-weight: 600; margin: 16px 0 24px; }
        a { display: block; padding: 12px 16px; border-radius: 8px; text-decoration: none; }
        .primary { background: #2f7dc3; color: #fff; font-weight: 600; }
        .secondary { margin-top: 12px; color: #4b5563; }
        @media (prefers-color-scheme: dark) {
            body { background: #16181d; color: #f3f4f6; }
            .secondary { color: #9ca3af; }
        }
    </style>
</head>
<body>
    <main>
        <img src="{{ config('ninja.app_logo') }}" alt="">
        <h1>{{ ctrans('texts.open_in_app') }}</h1>

        <a class="primary" id="app-link" href="{{ $appUrl }}"
           data-intent="{{ $intentUrl }}">{{ ctrans('texts.open_in_app') }}</a>
        <a class="secondary" id="web-link" href="{{ $webUrl }}">{{ ctrans('texts.continue_in_browser') }}</a>
    </main>

    <script>
        (function () {
            var link = document.getElementById('app-link');
            var web = document.getElementById('web-link').href;

            // Android only, and via intent:// rather than the scheme itself: an
            // unregistered scheme answers with ERR_UNKNOWN_URL_SCHEME, an error
            // page that replaces this document and takes the fallback below with
            // it. An intent URL carries its own browser_fallback_url instead.
            // Elsewhere the scheme stays behind a tap, where an unhandled one is
            // an alert rather than a navigation.
            var android = /Android/i.test(navigator.userAgent);

            if (android) {
                link.href = link.dataset.intent;
                window.location.href = link.href;
            }

            // If the app took the link this page is hidden and the timer aborts.
            // Longer where the button is the only route to the app: on Android
            // the intent URL has already decided, but elsewhere these seconds are
            // the whole window someone has to notice the button and press it.
            setTimeout(function () {
                if (document.visibilityState === 'visible') {
                    window.location.replace(web);
                }
            }, android ? 2000 : 4000);
        })();
    </script>
</body>
</html>
