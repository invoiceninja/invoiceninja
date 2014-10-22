@extends('public.header')

@section('content')
<section class="hero background hero-features center" data-speed="2" data-type="background">
  <div class="container">
    <div class="row">
      <h1><img src="{{ asset('images/icon-features.png') }}">{{ trans('public.features.header') }}</h1>
    </div>
  </div>
</section>

<section class="features features1">
  <div class="container">
    <div class="row">
      <div class="col-md-5 valign">

        <div class="headline">
          <div class="icon open"><span class="img-wrap"><img src="{{ asset('images/icon-opensource.png') }}"></span></div><h2>{{ trans('public.features.open_source') }}</h2>
        </div>
        <p class="first">{{ trans('public.features.open_source_text1') }}</p>
        <p>{{ trans('public.features.open_source_text2') }}</p>        
      </div>
      <div class="col-md-7">
        <img src="{{ asset('images/features1.jpg') }}">
      </div>
    </div>
  </div>
</section>

<section class="blue features">
  <div class="container">
    <div class="row">

      <div class="col-md-7">
        <img src="{{ asset('images/devices-2.png') }}">
      </div>
      <div class="col-md-5 valign">

        <div class="headline">
          <div class="icon free"><span class="img-wrap"><img src="{{ asset('images/icon-free2.png') }}"></span></div><h2>{{ trans('public.features.free_forever') }}</h2>
        </div>
        <p class="first">{{ trans('public.features.free_forever_text1') }}</p>
        <p>{{ trans('public.features.free_forever_text2') }}</p>      
      </div>

    </div>
  </div>
</section>
<section class="features features3">
  <div class="container">
    <div class="row">
      <div class="col-md-5">

        <div class="headline">
          <div class="icon secure"><span class="img-wrap"><img src="{{ asset('images/icon-secure.png') }}"></span></div><h2>{{ trans('public.features.secure') }}</h2>
        </div>
        <p class="first">{{ trans('public.features.secure_text1') }}</p>
        <p>{{ trans('public.features.secure_text2') }}</p>

      </div>
      <div class="col-md-7 valign">
        <img src="{{ asset('images/laptopwicon.jpg') }}">
      </div>
    </div>
  </div>
</section>
<section class="features features4">
  <div class="container">
    <div class="row">
      <div class="col-md-7">
        <img src="{{ asset('images/features4.jpg') }}">
      </div>
      <div class="col-md-5 valign">
        <div class="headline">
          <div class="icon pdf"><span class="img-wrap"><img src="{{ asset('images/icon-pdf.png') }}"></span></div><h2>{{ trans('public.features.live_pdf') }}</h2>
        </div>
        <p class="first">{{ trans('public.features.live_pdf_text1') }}</p>
        <p>{{ trans('public.features.live_pdf_text2') }}</p>
        <p><i>{{ trans('public.features.live_pdf_text3') }}</i></p>

      </div>
    </div>
  </div>
</section>
<section class="features features5">
  <div class="container">
    <div class="row">
      <div class="col-md-5 valign">
        <div class="headline">
          <div class="icon pay"><span class="img-wrap"><img src="{{ asset('images/icon-payment.png') }}"></span></div><h2>{{ trans('public.features.online_payments') }}</h2>
        </div>
        <p class="first">{{ trans('public.features.online_payments_text1') }}</p>
        <p>{{ trans('public.features.online_payments_text2') }}</p>
      </div>
      <div class="col-md-7">
        <img src="{{ asset('images/features5.jpg') }}">
      </div>
    </div>
  </div>
</section>
<section class="upper-footer features center">
  <div class="container">
    <div class="row">
      <div class="col-md-4">
        <h2 class="thin">{{ trans('public.features.footer') }}</h2>
      </div>
      <div class="col-md-4">
        <a href="#">
          <div class="cta">
            <h2 onclick="return getStarted()">{{ trans('public.features.footer_action') }}</h2>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>


@stop
