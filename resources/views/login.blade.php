@extends('master')

@section('head')
    @if (!empty($clientauth) && $fontsUrl = Utils::getAccountFontsUrl())
        <link href="{!! $fontsUrl !!}" rel="stylesheet" type="text/css">
    @endif
    <link href="{{ asset('css/built.public.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.login.css') }}" rel="stylesheet" type="text/css"/>

    @if (!empty($clientauth))
        <style type="text/css">{!! Utils::clientViewCSS() !!}</style>
    @endif
@endsection

@section('body')
    @if (!Utils::isWhiteLabel())
        <div class="container-fluid">
            <div class="row header">
                <div class="col-md-6 col-xs-12 text-center">
                    <a href="https://www.invoiceninja.com/" target="_blank">
                        <img width="231" src="{{ asset('images/invoiceninja-logox53.png') }}"/>
                    </a>
                </div>
                <div class="col-md-6 text-right visible-lg">
                    <p>{{trans('texts.ninja_tagline')}}</p>
                </div>
            </div>
        </div>
    @endif

    @yield('form')
@endsection
