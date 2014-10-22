@extends('public.header')

@section('content')

<section class="hero background hero-secure center" data-speed="2" data-type="background">
  <div class="container">
    <div class="row">
      <h1>License Key</h1>
      <!--<p class="thin"><img src="{{ asset('images/icon-secure-pay.png') }}">256-BiT Encryption</p>-->
      <!-- <img src="{{ asset('images/providers.png') }}"> -->
    </div>
  </div>
</section>

<p>&nbsp;</p>

<section class="faq">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
      <h3 style="text-align:center">{{ $message }}</h3>
      <p>&nbsp;</p>
      <p>&nbsp;</p>
      <h2 style="text-align:center">{{ $license }}</h2>      
      </div>
    </div>
  </div>
</section>

<p>&nbsp;</p>


@stop