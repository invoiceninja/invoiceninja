@extends('public.header')

@section('content') 


<section class="hero background hero1 center" data-speed="2" data-type="background">
  <div class="caption-side"></div>

  <div class="container">
    <div class="row" style="margin:0;">
      <div class="caption-wrap">
        <div class="caption">
          <h1>{{ trans('public.home.header') }}</h1>
          <p>{{ trans('public.home.sub_header') }}</p>
        </div>
      </div>
    </div>
  </div>

  
  <div class="container">
    <div class="row">
      <div class="col-md-3 center-block">
        <a href="#">
          <div class="cta">
            <h2 id="startButton" onclick="return getStarted()">{{ trans('public.invoice_now') }} <span>+</span></h2>
          </div>
        </a>
      </div>
    </div>
  </div>

  
</section>

<section class="features-splash">
  <div class="container">
    <div class="row">
      <div class="col-md-3 one">
        <div class="box">
          <div class="icon free"><span class="img-wrap"><img src="{{ asset('images/icon-free.png') }}"></span></div>
          <h2>{{ trans('public.home.free_always') }}</h2>
          <p>{{ trans('public.home.free_always_text') }}</p>              
        </div>
      </div>

      <div class="col-md-3 two">
        <div class="box">
          <div class="icon open"><span class="img-wrap"><img src="{{ asset('images/icon-opensource.png') }}"></span></div>
          <h2>{{ trans('public.home.open_source') }}</h2>
          <p>{{ trans('public.home.open_source_text') }}</p>              
        </div>
      </div>

      <div class="col-md-3 three">
        <div class="box">
          <div class="icon pdf"><span class="img-wrap"><img src="{{ asset('images/icon-pdf.png') }}"></span></div>
          <h2>{{ trans('public.home.live_pdf') }}</h2>
          <p>{{ trans('public.home.live_pdf_text') }}</p>              
        </div>
      </div>

      <div class="col-md-3 four">
        <div class="box">
          <div class="icon pay"><span class="img-wrap"><img src="{{ asset('images/icon-payment.png') }}"></span></div>
          <h2>{{ trans('public.home.online_payments') }}</h2>
          <p>{{ trans('public.home.online_payments_text') }}</p>              
          <p></p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="blue">
  <div class="container">
    <div class="row">
      <div class="col-md-5">
       <h1>{{ trans('public.home.footer') }}</h1>
       <div class="row">
        <div class="col-md-7">
          <a href="#">
            <div class="cta">
              <h2 onclick="return getStarted()">{{ trans('public.invoice_now') }} <span>+</span></h2>
            </div>
          </a>

        </div>
      </div>
      <p>{{ trans('public.no_signup_needed') }}</p>
    </div>
    <div class="col-md-7">
      <img src="{{ asset('images/devices.png') }}">
    </div>
  </div>
</div>
</section>


@stop