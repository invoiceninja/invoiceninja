@extends('master')

@section('head')    
<link href="{{ asset('css/bootstrap.splash.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/splash.css') }}" rel="stylesheet" type="text/css"/>    
<link href="{{ asset('images/apple-touch-icon-114x114-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="114x114">
<link href="{{ asset('images/apple-touch-icon-72x72-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="72x72">
<link href="{{ asset('images/apple-touch-icon-57x57-precomposed.png') }}" rel="apple-touch-icon-precomposed">
@stop

@section('body')

<div class="navbar" style="margin-bottom:0px">
  <div class="container">
    <div class="navbar-inner">
      <a class="brand" href="#"><img src=
        "images/invoiceninja-logo.png"></a>
        <ul class="navbar-list">
          <li>{{ link_to('login', Auth::check() ? 'Continue' : 'Login' ) }}</li>
        </ul>
      </div>
    </div>
  </div>
</div>

@stop