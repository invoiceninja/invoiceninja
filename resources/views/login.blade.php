@extends('master')

@section('head')
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.public.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.login.css') }}" rel="stylesheet" type="text/css"/>
@endsection

@section('body')
    @if (!Utils::isWhiteLabel())
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
