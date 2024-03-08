<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
    @if(\App\Utils\Ninja::isHosted())
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-WMJ5W23');</script>
    <!-- End Google Tag Manager -->
    @endif

        <!-- Error: {{ session('error') }} -->

        @if (config('services.analytics.tracking_id'))
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
        @auth('contact')
            <title>@yield('meta_title', '') — {{ auth()->guard('contact')->user()->user->account->isPaid() ? auth()->guard('contact')->user()->company->present()->name() : 'Invoice Ninja' }}</title>
        @endauth

        @guest
            <title>@yield('meta_title', '') — {{ config('app.name') }}</title>
        @endguest

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
        @if(auth()->guard('contact')->user() && !auth()->guard('contact')->user()->user->account->isPaid())
        {{-- <link href="{{ mix('favicon.png') }}" rel="shortcut icon" type="image/png"> --}}
        @endif

        <link rel="canonical" href="{{ config('ninja.app_url') }}/{{ request()->path() }}"/>

        {{-- Feel free to push anything to header using @push('header') --}}
        @stack('head')

        @livewireStyles

        <link rel="stylesheet" type="text/css" href="{{ asset('vendor/cookieconsent@3/cookieconsent.min.css') }}" />
    </head>

    <body class="antialiased">
    @if(\App\Utils\Ninja::isHosted())
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WMJ5W23"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    @endif

        @if(session()->has('message'))
            <div class="py-1 text-sm text-center text-white bg-primary disposable-alert">
                {{ session('message') }}
            </div>
        @endif

        @yield('body')

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
    </body>

    <footer>
        @yield('footer')
        @stack('footer')
    </footer>

</html>
