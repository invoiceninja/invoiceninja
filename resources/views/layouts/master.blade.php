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
        <script>
            Vue.config.devtools = true;
        </script>
    @else
        <script>
            function gtag(){}
        </script>   
    @endif

    <meta charset="utf-8">
    <title>@yield('meta_title', 'Invoice Ninja') | {{ config('app.name') }}</title>
    <meta name="description" content="@yield('meta_description')"/>
    <link href="{{ asset('favicon.png') }}" rel="shortcut icon" type="image/png">

    <!--
    TODO Setup social sharing info
    <meta property="og:site_name" content="Invoice Ninja"/>
    <meta property="og:url" content="{{ config('ninja.app_url') }}"/>
    <meta property="og:title" content="Invoice Ninja"/>
    <meta property="og:image" content="{{ config('ninja.app_url') }}images/logo.png"/>
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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.4.1/css/simple-line-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="canonical" href="{{ config('ninja.app_url') }}/{{ request()->path() }}"/>
    <link rel="stylesheet" href="{{ mix('/css/ninja.min.css') }}">
    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <script src=" {{ mix('/js/coreui.min.js') }}"></script>
    <script defer src="/js/lang.js"></script>
    <style type="text/css">
        .bg-primary2 {
         background-color: #167090 !important;
         color: #fff;
       }
   
        a.bg-primary2:hover, a.bg-primary:focus,
        button.bg-primary:hover,
        button.bg-primary:focus {
            background-color: #56b3d4 !important;
        }

    </style>
    @yield('head')
</head>

@include('header', $header)
@yield('header')

@include('sidebar')
@yield('sidebar')

@section('body')
@yield('body')

@include('dashboard.aside')

@include('footer')
@yield('footer')
</html>