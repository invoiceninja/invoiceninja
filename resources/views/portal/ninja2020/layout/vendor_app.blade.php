<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <!-- Error: {{ session('error') }} -->
        @if (isset($company) && $company->matomo_url && $company->matomo_id)
            <script>
                var _paq = window._paq = window._paq || [];
                /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
                _paq.push(['trackPageView']);
                _paq.push(['enableLinkTracking']);
                @if (auth()->guard('vendor')->check())
                _paq.push(['setUserId', '{{ auth()->guard('vendor')->user()->present()->name() }}']);
                @endif
                (function() {
                    var u="{{ $company->matomo_url }}";
                    _paq.push(['setTrackerUrl', u+'matomo.php']);
                    _paq.push(['setSiteId', '{{ $company->matomo_id }}']);
                    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
                    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
                })();
            </script>
            <noscript><p><img src="{{ $company->matomo_url }}/matomo.php?idsite={{ $company->matomo_id }}&amp;rec=1" style="border:0;" alt="" /></p></noscript>
        @elseif (config('services.analytics.tracking_id'))
            <script async src="https://www.googletagmanager.com/gtag/js?id=UA-122229484-1"></script>
            <script>
                window.dataLayer = window.dataLayer || [];

                function gtag() {
                    dataLayer.push(arguments);
                }

                gtag('js', new Date());
                gtag('config', '{{ config('services.analytics.tracking_id') }}', {'anonymize_ip': true});

                function trackEvent(category, action) {
                    ga('send', 'event', category, action, this.src);
                }
            </script>
        @else
            <script>
                function gtag() {
                }
            </script>
        @endif

        <!-- Title -->
        @if(isset($company->account) && !$company->account->isPaid())
            <title>@yield('meta_title', '') — Invoice Ninja</title>
        @elseif(isset($company) && !is_null($company))
            <title>@yield('meta_title', '') — {{ $company->present()->name() }}</title>
        @else
            <title>@yield('meta_title', '')</title>
        @endif

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="@yield('meta_description')"/>

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Scripts -->
        @vite('resources/js/app.js')

        <!-- Fonts -->
        {{-- <link rel="dns-prefetch" href="https://fonts.gstatic.com"> --}}
        {{-- <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet" type="text/css" defer> --}}
        <style>
            @font-face {
              font-family: 'Open Sans';
              font-style: normal;
              font-weight: 400;
              font-stretch: 100%;
              font-display: swap;
              src: url( {{asset('css/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2')}}) format('woff2');
              unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }
        </style>

        <!-- Styles -->
        @vite('resources/sass/app.scss')

        @if(auth()->guard('vendor')->user() && !auth()->guard('vendor')->user()->user->account->isPaid())
            <link href="{{ asset('favicon.png') }}" rel="shortcut icon" type="image/png">
        @endif

        <link rel="canonical" href="{{ config('ninja.site_url') }}/{{ request()->path() }}"/>

        @if((bool) \App\Utils\Ninja::isSelfHost())
            <style>
                {!! $settings->portal_custom_css !!}
            </style>
        @endif

        @livewireStyles

        {{-- Feel free to push anything to header using @push('header') --}}
        @stack('head')

        @if((isset($company) && $company->account->isPaid() && !empty($settings->portal_custom_head)) || ((bool) \App\Utils\Ninja::isSelfHost() && !empty($settings->portal_custom_head)))
            <div class="py-1 text-sm text-center text-white bg-primary">
                {!! $settings->portal_custom_head !!}
            </div>
        @endif

        <link rel="stylesheet" type="text/css" href="{{ asset('vendor/cookieconsent@3/cookieconsent.min.css') }}" />
    </head>

    @include('portal.ninja2020.components.primary-color')

    <body class="antialiased">
        @if(session()->has('message'))
            <div class="py-1 text-sm text-center text-white bg-primary disposable-alert">
                {{ session('message') }}
            </div>
        @endif

        @component('portal.ninja2020.components.general.sidebar.vendor_main', ['settings' => $settings, 'sidebar' => $sidebar])
            @yield('body')
        @endcomponent

        @livewireScriptConfig 

        <script src="{{ asset('vendor/cookieconsent@3/cookieconsent.min.js') }}" data-cfasync="false"></script>
        <script>
            window.addEventListener("load", function(){
                if (! window.cookieconsent) {
                    return;
                }
                window.cookieconsent.initialise({
                    "palette": {
                        "popup": {
                            "background": "#000"
                        },
                        "button": {
                            "background": "#f1d600"
                        },
                    },
                    "content": {
                        "href": "{{ config('ninja.privacy_policy_url.hosted') }}",
                        "message": "{{ ctrans('texts.cookie_message')}}",
                        "dismiss": "{{ ctrans('texts.got_it')}}",
                        "link": "{{ ctrans('texts.learn_more')}}",
                    }
                })}
            );
        </script>

        @if($company && $company->google_analytics_key)
            <script>
                (function (i, s, o, g, r, a, m) {
                    i['GoogleAnalyticsObject'] = r;
                    i[r] = i[r] || function () {
                                (i[r].q = i[r].q || []).push(arguments)
                            }, i[r].l = 1 * new Date();
                    a = s.createElement(o),
                            m = s.getElementsByTagName(o)[0];
                    a.async = 1;
                    a.src = g;
                    m.parentNode.insertBefore(a, m)
                })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

                ga('create', '{{ $company->google_analytics_key }}', 'auto');
                ga('set', 'anonymizeIp', true);
                ga('send', 'pageview');

                function trackEvent(category, action) {
                    ga('send', 'event', category, action, this.src);
                }
            </script>
        @endif

    </body>

    <footer>
        @yield('footer')
        @stack('footer')

        @if($company && $company->account->isPaid() && !empty($settings->portal_custom_footer))
            <div class="py-1 text-sm text-center text-white bg-primary">
                {!! $settings->portal_custom_footer !!}
            </div>
        @endif
    </footer>

    @if((bool) \App\Utils\Ninja::isSelfHost())
        <script>
            {!! $settings->portal_custom_js !!}
        </script>
    @endif
</html>
