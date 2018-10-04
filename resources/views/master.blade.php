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
    <meta property="og:image" content="{{ config('ninja.site_url') }}/images/round_logo.png"/>
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
    --/>

    <!-- http://stackoverflow.com/questions/19012698/browser-cache-issues-in-laravel-4-application -->
    <meta http-equiv="cache-control" content="max-age=0"/>
    <meta http-equiv="cache-control" content="no-cache"/>
    <meta http-equiv="cache-control" content="no-store"/>
    <meta http-equiv="cache-control" content="must-revalidate"/>
    <meta http-equiv="expires" content="0"/>
    <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT"/>
    <meta http-equiv="pragma" content="no-cache"/>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="canonical" href="{{ config('ninja.app_url') }}{{ request()->path() }}"/>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.7.1/css/bulma.css"/>
    <script defer src="https://use.fontawesome.com/releases/v5.1.0/js/all.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/core.js"></script>

</head>
<body>

    @include('header')

    <div class="section">
        <div class="columns">

            @include('sidebar')

            <main class="column">
                <div class="level">
                    <div class="level-left">
                        <div class="level-item">
                            <div class="title">Dashboard</div>
                        </div>
                    </div>
                    <div class="level-right">
                        <div class="level-item">
                            <button type="button" class="button is-small">
                                March 8, 2017 - April 6, 2017
                            </button>
                        </div>
                    </div>
                </div>

                <div class="columns is-multiline">
                    <div class="column">
                        <div class="box">
                            <div class="heading">Top Seller Total</div>
                            <div class="title">56,950</div>
                            <div class="level">
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Sales $</div>
                                        <div class="title is-5">250,000</div>
                                    </div>
                                </div>
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Overall $</div>
                                        <div class="title is-5">750,000</div>
                                    </div>
                                </div>
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Sales %</div>
                                        <div class="title is-5">25%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="box">
                            <div class="heading">Revenue / Expenses</div>
                            <div class="title">55% / 45%</div>
                            <div class="level">
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Rev Prod $</div>
                                        <div class="title is-5">30%</div>
                                    </div>
                                </div>
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Rev Serv $</div>
                                        <div class="title is-5">25%</div>
                                    </div>
                                </div>
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Exp %</div>
                                        <div class="title is-5">45%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="box">
                            <div class="heading">Feedback Activity</div>
                            <div class="title">78% &uarr;</div>
                            <div class="level">
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Positive</div>
                                        <div class="title is-5">1560</div>
                                    </div>
                                </div>
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Negative</div>
                                        <div class="title is-5">368</div>
                                    </div>
                                </div>
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Pos/Neg %</div>
                                        <div class="title is-5">77% / 23%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="box">
                            <div class="heading">Orders / Returns</div>
                            <div class="title">75% / 25%</div>
                            <div class="level">
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Orders $</div>
                                        <div class="title is-5">425,000</div>
                                    </div>
                                </div>
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Returns $</div>
                                        <div class="title is-5">106,250</div>
                                    </div>
                                </div>
                                <div class="level-item">
                                    <div class="">
                                        <div class="heading">Success %</div>
                                        <div class="title is-5">+ 28,5%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="columns is-multiline">
                    <div class="column is-6">
                        <div class="panel">
                            <p class="panel-heading">
                                Expenses: Daily - $700
                            </p>
                            <div class="panel-block">
                                <figure class="image is-16x9">
                                    <img src="https://placehold.it/1280x720">
                                </figure>
                            </div>
                        </div>
                    </div>
                    <div class="column is-6">
                        <div class="panel">
                            <p class="panel-heading">
                                Refunds: Daily - $200
                            </p>
                            <div class="panel-block">
                                <figure class="image is-16x9">
                                    <img src="https://placehold.it/1280x720">
                                </figure>
                            </div>
                        </div>
                    </div>
                    <div class="column is-6">
                        <div class="panel">
                            <p class="panel-heading">
                                Something
                            </p>
                            <div class="panel-block">
                                <figure class="image is-16x9">
                                    <img src="https://placehold.it/1280x720">
                                </figure>
                            </div>
                        </div>
                    </div>
                    <div class="column is-6">
                        <div class="panel">
                            <p class="panel-heading">
                                Something Else
                            </p>
                            <div class="panel-block">
                                <figure class="image is-16x9">
                                    <img src="https://placehold.it/1280x720">
                                </figure>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
