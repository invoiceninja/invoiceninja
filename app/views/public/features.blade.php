@extends('public.header')

@section('content')
<section class="hero background hero5 center" data-speed="2" data-type="background">
  <div class="container">
    <div class="row">
          <h1><img src="{{ asset('images/icon-features.png') }}"><span class="thin">THE</span> FEATURES</h1>
        </div>
      </div>
    </section>

 <section class="features features1">
  <div class="container">
    <div class="row">
      <div class="col-md-5">
          <div class="headline">
        <div class="icon open"><span class="img-wrap"><img src="{{ asset('images/icon-opensource.png') }}"></span></div><h2>Open Source Platform</h2>
              </div>
       <p class="first">Set the code free! Here at Invoice Ninja, we’re all about being non-evil, and providing full code transparency is a central manifestation of this value.</p>
          <p>Our users started seeing the benefits of open source within days of our launch, when we rolled out v1.0.2, which included some key code improvements that our friends on GitHub sent our way.
</p>
          <p>We firmly believe that being an open source product helps everyone involved. We’re looking forward to seeing what the developers out there can do to take Invoice Ninja into new realms of usefulness.</p>
      
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
                <div class="col-md-5">
                    <div class="headline">
               <div class="icon free"><span class="img-wrap"><img src="{{ asset('images/icon-free2.png') }}"></span></div><h2>FREE. Forever.</h2>
                        </div>
                   <p class="first">Set the code free! Here at Invoice Ninja, we’re all about being non-evil, and providing full code transparency is a central manifestation of this value.</p>
          <p>Our users started seeing the benefits of open source within days of our launch, when we rolled out v1.0.2, which included some key code improvements that our friends on GitHub sent our way.
</p>
          <p>We firmly believe that being an open source product helps everyone involved. We’re looking forward to seeing what the developers out there can do to take Invoice Ninja into new realms of usefulness.</p>
              </div>
            </div>
          </div>
        </section>
 <section class="features features1">
  <div class="container">
    <div class="row">
      <div class="col-md-5">
          <div class="headline">
        <div class="icon open"><span class="img-wrap"><img src="{{ asset('images/icon-opensource.png') }}"></span></div><h2>Open Source Platform</h2>
              </div>
       <p class="first">Set the code free! Here at Invoice Ninja, we’re all about being non-evil, and providing full code transparency is a central manifestation of this value.</p>
          <p>Our users started seeing the benefits of open source within days of our launch, when we rolled out v1.0.2, which included some key code improvements that our friends on GitHub sent our way.
</p>
          <p>We firmly believe that being an open source product helps everyone involved. We’re looking forward to seeing what the developers out there can do to take Invoice Ninja into new realms of usefulness.</p>
      
    </div>
        <div class="col-md-7">
             <img src="{{ asset('images/features1.jpg') }}">
        </div>
    </div>
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