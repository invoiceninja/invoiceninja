<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <!-- Source: https://github.com/invoiceninja/invoiceninja -->
    <!-- Error: {{ session('error') }} -->

    @if (config('services.analytics.tracking_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-122229484-1"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());
        gtag('config', '{{ config('
            services.analytics.tracking_id ') }}', {
                'anonymize_ip': true
            });

        function trackEvent(category, action) {
            ga('send', 'event', category, action, this.src);
        }
    </script>
    <script>
        Vue.config.devtools = true;
    </script>
    @else
    <script>
        function gtag() {}
    </script>
    @endif

    <!-- Title -->
    <title>@yield('meta_title', 'Invoice Ninja') | {{ config('app.name') }}</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description')" />

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Scripts -->
    <script src="{{ mix('js/app.js') }}" defer></script>
    <script src="https://kit.fontawesome.com/8a87eb8352.js" crossorigin="anonymous"></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet" type="text/css">

    <!-- Styles -->
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
    {{-- <link href="{{ mix('favicon.png') }}" rel="shortcut icon" type="image/png"> --}}

    <link rel="canonical" href="{{ config('ninja.app_url') }}/{{ request()->path() }}" />

    {{-- Feel free to push anything to header using @push('header') --}}
    @stack('head')

    {!! $client->getSetting('portal.custom_head') !!}

    <style>
        {!! $client->getSetting('portal.custom_css') !!}
    </style>
</head>

<body class="antialiased">
    @yield('body')
</body>

<footer>
    @yield('footer')
    @stack('footer')

    {!! $client->getSetting('portal.custom_footer') !!}
</footer>

<script>
    // .. Any custom definied scripts from admin portal go here.
    {!! $client->getSetting('portal.custom_js') !!}
</script>

</html>