@extends('public.header')

@section('content')
<section class="hero background hero5" data-speed="2" data-type="background">
  <div class="caption-side"></div>
  <div class="container">
    <div class="row" style="margin:0;">
      <div class="caption-wrap">
        <div class="caption">
          <h1>THE <span style="color:#ecd816">FEATURES</span>
            </div>
          </div>
        </div>
      </div>
    </section>

 <section class="about center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Open Source Platform</h2>
        <p>Set the code free! Here at Invoice Ninja, we’re all about being non-evil, and providing full code transparency is a central manifestation of this value. Our users started seeing the benefits of open source within days of our launch, when we rolled out v1.0.2, which included some key code improvements that our friends on GitHub sent our way.</p>
          <p>We firmly believe that being an open source product helps everyone involved. We’re looking forward to seeing what the developers out there can do to take Invoice Ninja into new realms of usefulness.</p>
      </div>
    </div>
  </div>
</section>

<section class="about white-bg">
  <div class="container">
    <div class="row">
      <div class="col-md-5">
        <div class="screendump">
          <img src="images/features1.jpg">
        </div>
      </div>
      <div class="col-md-7">
        <h2>Free Forever</h2>
        <p>Yeah, you read that correctly. You don’t have to pay us a cent to use our tools. We know how tough it is to make ends meet as a web-based business, and we’re bent on providing a top-notch product that will do everything you need it to do, without any subscription or opt-in fees. 
        </p>
        <p>
        Try Invoice Ninja out. You literally have nothing to lose. We’re confident that you’ll find the experience so positive that you’ll never need to turn elsewhere.
        </p>
      </div>
    </div>
  </div>
</section>
<section class="about">
  <div class="container">
    <div class="row">
      <div class="col-md-7">
        <h2>Secure and Private</h2>
        <p>Invoice Ninja has been built from the ground up to keep your data safe. Only you have access to your login and accounting details, and we will never share your transaction data to any third party.</p>
        <p>
        Our website operates with <span class="blue-text">256-bit encryption</span>, which is even more secure than most banking websites. Invoice Ninja uses the <span class="blue-text">TLS 1.0 cryptographic protocol</span>, <span class="blue-text">AES_256_CBC string encryption</span>, <span class="blue-text">SHA1 message authentication</span> and <span class="blue-text">DHE_RSA key exchanges</span>. We feel safe here and have invested heavily in measures to ensure that you do too.  
        </p>
      </div>
      <div class="col-md-5">
        <div class="screendump">
          <img src="images/features2.jpg">
        </div>
      </div>
    </div>
  </div>
</section>
<section class="about white-bg">
  <div class="container">
    <div class="row">
      <div class="col-md-5">
        <div class="screendump">
          <img src="images/features3.jpg">
        </div>
      </div>
      <div class="col-md-7">
        <h2>Live PDF Creation</h2>
        <p>With Invoice Ninja, we’ve done away with the need for cumbersome multi-click invoice previewing after each save. When you enter the details of your customer and/or invoice in our editor, you can instantly see the results in the pdf preview pane below. Want to see what your invoice would look like in a different layout style? The live pdf can show you four beautiful preset styles in real time too. 
        </p>
        <p>
       Just create, save, send, and you’re done!
        </p>
      </div>
    </div>
  </div>
</section>
<section class="about">
  <div class="container">
    <div class="row">
      <div class="col-md-7">
        <h2>Online Payments</h2>
        <p>Invoice Ninja seamlessly integrates with all of the top internet payment processors and gateways so you can get paid for your work quickly and easily. Invoices crated with our tools aren’t just for bookkeeping purposes - they bring in the Benjamins.</p>
        <p>
        We also make it super easy to choose the right gateway for the specific needs of your business and are happy to help you to get started working with the gateway of your choice. What’s more, we’re constantly working on rolling out additional gateway integrations, so if you don’t see the one you use here, just let us know, and there’s a good chance we’ll add it for you.
        </p>
      </div>
      <div class="col-md-5">
        <div class="screendump">
          <img src="images/features4.jpg">
        </div>
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