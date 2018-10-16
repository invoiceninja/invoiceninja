<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>

    <!-- Source: https://github.com/invoiceninja/invoiceninja -->
    <!-- Error: {{ session('error') }} -->

    @if (config('services.analytics.tracking_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id=UA-122229484-1"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ config('services.analytics.tracking_id') }}', { 'anonymize_ip': true });
            function trackEvent(category, action) {
                ga('send', 'event', category, action, this.src);
            }
        </script>
    @else
        <script>
            function gtag(){}
        </script>
    @endif

    <meta charset="utf-8">
    <title>@yield('meta_title') | {{ config('app.name') }}</title>
    <meta name="description" content="@yield('meta_description')"/>
    <link href="{{ asset('favicon.png') }}" rel="shortcut icon" type="image/png">

    <!--
    TODO Setup social sharing info
    <meta property="og:site_name" content="Invoice Ninja"/>
    <meta property="og:url" content="{{ config('ninja.site_url') }}"/>
    <meta property="og:title" content="Invoice Ninja"/>
    <meta property="og:image" content="{{ config('ninja.site_url') }}/images/logo.png"/>
    <meta property="og:description" content="Create. Send. Get Paid."/>
    --/>
    <!-- http://realfavicongenerator.net -->
    <!--
    TODO Setup favicon
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" href="{{ url('favicon-32x32.png') }}" sizes="32x32">
    <link rel="icon" type="image/png" href="{{ url('favicon-16x16.png') }}" sizes="16x16">
    <link rel="manifest" href="{{ url('manifest.json') }}">
    <link rel="mask-icon" href="{{ url('safari-pinned-tab.svg') }}" color="#3bc65c">
    <link rel="shortcut icon" href="{{ url('favicon.ico') }}">
    <meta name="apple-mobile-web-app-title" content="Invoice Ninja">
    <meta name="application-name" content="Invoice Ninja">
    <meta name="theme-color" content="#ffffff">
    -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="keyword" content="Bootstrap,Admin,Template,Open,Source,jQuery,CSS,HTML,RWD,Dashboard">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="stylesheet" href="https://unpkg.com/@coreui/icons/css/coreui-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.4.1/css/simple-line-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.js"></script>
    <link rel="canonical" href="{{ config('ninja.app_url') }}/{{ request()->path() }}"/>
    <link rel="stylesheet" href="{{ mix('/css/ninja.css') }}">
    <script src=" {{ mix('/js/ninja.js') }}"></script>

    @yield('head')

</head>

@yield('header')

@yield('sidebar')

@yield('body')

@yield('footer')


</html>