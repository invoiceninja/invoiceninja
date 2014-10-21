@extends('public.header')

@section('content')

<section class="hero background hero-plans" data-speed="2" data-type="background">
 <div class="container">
  <div class="row">
    <h1><img src="{{ asset('images/icon-plans.png') }}">{{ trans('public.plans.header') }}</h1>
  </div>
</div>
</section>

<section class="plans center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>{{ trans('public.plans.go_pro') }}</h2>
        <p>{{ trans('public.plans.go_pro_text') }}</p>
        </div>
      </div>
    </div>

    <div class="container">
      @include('plans')
    </div>

  </div>
</section>

<section class="upper-footer white-bg">
  <div class="container">
    <div class="row">
      <div class="col-md-3 center-block">
        <a href="#">
          <div class="cta">
            <h2 onclick="return getStarted()">{{ trans('public.invoice_now') }} <span>+</span></h2>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>



@stop