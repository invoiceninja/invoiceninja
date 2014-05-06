@extends('public.header')

@section('content')
<section class="hero background hero-features center" data-speed="2" data-type="background">
  <div class="container">
    <div class="row">
          <h1><img src="{{ asset('images/icon-features.png') }}"><span class="thin">THE</span> FEATURES</h1>
        </div>
      </div>
    </section>

 <section class="features features1">
  <div class="container">
    <div class="row">
      <div class="col-md-5 valign">
            
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
                <div class="col-md-5 valign">
                   
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
 <section class="features features3">
  <div class="container">
    <div class="row">
      <div class="col-md-5">
    
          <div class="headline">
        <div class="icon secure"><span class="img-wrap"><img src="{{ asset('images/icon-secure.png') }}"></span></div><h2>Secure & Private</h2>
              </div>
       <p class="first">Invoice Ninja has been built from the ground up to keep your data safe. Only you have access to your login & accounting details, & we will never share your transaction data to any third party.</p>
          <p>Our website operates with 256-bit encryption, which is even more secure than most banking websites. Invoice Ninja uses the TLS 1.0 cryptographic protocol, AES_256_CBC string encryption, SHA1 message authentication and DHE_RSA key exchanges. We feel safe here and have invested heavily in measures to ensure that you do too.</p>
      
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
        <div class="icon pdf"><span class="img-wrap"><img src="{{ asset('images/icon-pdf.png') }}"></span></div><h2>Live .PDF View</h2>
              </div>
       <p class="first">With Invoice Ninja, we’ve done away with the need for cumbersome multi-click invoice previewing after each save.</p>
          <p>When you enter the details of your customer and/or invoice in our editor, you can instantly see the results in the pdf preview pane below. Want to see what your invoice would look like in a different layout style? The live pdf can show you four beautiful preset styles in real time too.
</p><p><i>Just create, save, send, and you’re done!</i></p>
      
    </div>
    </div>
  </div>
</section>
<section class="features features5">
  <div class="container">
    <div class="row">
      <div class="col-md-5 valign">
          <div class="headline">
        <div class="icon pay"><span class="img-wrap"><img src="{{ asset('images/icon-payment.png') }}"></span></div><h2>Online Payments</h2>
              </div>
       <p class="first">Invoice Ninja seamlessly integrates with all of the top internet payment processors and gateways so you can get paid for your work quickly and easily.</p>
          <p>Invoices crated with our tools aren’t just for bookkeeping purposes - they bring in the Benjamins. We also make it super easy to choose the right gateway for the specific needs of your business and are happy to help you to get started working with the gateway of your choice. What’s more, we’re constantly working on rolling out additional gateway integrations, so if you don’t see the one you use here, just let us know, and there’s a good chance we’ll add it for you. </p>
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
        <h2 class="thin">Like what you see?</h2>
            </div>
      <div class="col-md-4">
        <a href="#">
          <div class="cta">
            <h2 onclick="return getStarted()">Get started today!</h2>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>


@stop