@extends('public.header')

@section('content')

<section class="hero background hero-plans" data-speed="2" data-type="background">
 <div class="container">
  <div class="row">
    <h1><img src="{{ asset('images/icon-plans.png') }}"><span class="thin">The</span> plans</h1>
  </div>
</div>
</section>

<section class="plans center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Go Pro to Unlock Premium Invoice Ninja Features</h2>
        <p>We believe that the free version of Invoice Ninja is a truly awesome product loaded 
          with the key features you need to bill your clients electronically. But for those who 
          crave still more Ninja awesomeness, we've unmasked the Invoice Ninja Pro plan, which 
          offers more versatility, power and customization options for just $50 per year. </p>
        </div>
      </div>
    </div>

    @include('plans')

  </div>
</section>

<section class="upper-footer white-bg">
  <div class="container">
    <div class="row">
      <div class="col-md-3 center-block">
        <a href="#">
          <div class="cta">
            <h2 onclick="return getStarted()">Invoice Now <span>+</span></h2>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>



@stop