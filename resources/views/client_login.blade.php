@extends('master')

@section('head')
    @if (!empty($clientauth) && $fontsUrl = Utils::getAccountFontsUrl())
        <link href="{{ $fontsUrl }}" rel="stylesheet" type="text/css">
    @endif
    <link href="{{ asset('css/built.public.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/bootstrap.min.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/built.login.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

    @if (!empty($clientauth))
        <style type="text/css">{!! Utils::clientViewCSS() !!}</style>
    @endif
@endsection

@section('body')
    @yield('form')
@endsection
