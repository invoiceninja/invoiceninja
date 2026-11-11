<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <script>
            window.gtag = window.gtag || function () {};
            window.trackEvent = window.trackEvent || function () {};
        </script>

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
                {!! app(\App\Services\ClientPortal\PortalHtmlSanitizer::class)->clean($settings->portal_custom_head) !!}
            </div>
        @endif

    </head>

    @include('portal.ninja2020.components.primary-color')

    <body class="antialiased">
        @if(session()->has('message'))
            <div class="py-1 text-sm text-center text-white bg-primary disposable-alert">
                {{ session('message') }}
            </div>
        @endif

        @component('portal.ninja2020.components.general.sidebar.vendor_main', ['settings' => $settings, 'sidebar' => $sidebar, 'company' => $company])
            @yield('body')
        @endcomponent

        @livewireScriptConfig 

    </body>

    <footer>
        @yield('footer')
        @stack('footer')

        @if($company && $company->account->isPaid() && !empty($settings->portal_custom_footer))
            <div class="py-1 text-sm text-center text-white bg-primary">
                {!! app(\App\Services\ClientPortal\PortalHtmlSanitizer::class)->clean($settings->portal_custom_footer) !!}
            </div>
        @endif
    </footer>

    @if((bool) \App\Utils\Ninja::isSelfHost())
        <script>
            {!! $settings->portal_custom_js !!}
        </script>
    @endif
</html>
