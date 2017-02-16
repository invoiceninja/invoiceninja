@extends('master')

@section('head')
    @if (!empty($clientauth) && !empty($clientFontUrl))
        <link href="{!! $clientFontUrl !!}" rel="stylesheet" type="text/css">
    @endif
    <link href="{{ asset('css/built.public.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

    @if (!empty($clientauth) && !empty($account))
    <style type="text/css">{!! $account->clientViewCSS() !!}</style>
    @endif

    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.login.css') }}" rel="stylesheet" type="text/css"/>
@endsection

@section('body')
    @if (!Utils::isWhiteLabel() || empty($clientauth))
        <div class="container-fluid">
            <div class="row header">
                <div class="col-md-6 col-xs-12 text-center">
                    <a><img width="231" src="{{ asset('images/invoiceninja-logox53.png') }}"/></a>
                </div>
                <div class="col-md-6 text-right visible-lg">
                    <p>{{trans('texts.ninja_tagline')}}</p>
                </div>
            </div>
        </div>
    @endif

    @yield('form')
@endsection
