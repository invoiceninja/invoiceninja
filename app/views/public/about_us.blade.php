@extends('public.header')

@section('content')
  <section class="hero3" data-speed="2" data-type="background">
    <div class="container">
      <div class="caption">
       <h1>WHY INVOICE NINJA?
       </h1>
     </div>
   </div>
 </section>

 <section class="about center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Open Source Platform</h2>
        <p>Free yourself from online invoicing platforms with high monthly fees and limited functionality.  Being <a href="https://github.com/hillelcoren/invoice-ninja" target="_blank">open source</a> allows us fast app development, security audits by the open-course community, and we can keep it <strong>FREE!</strong></p>
      </div>
    </div>
  </div>
</section>

<section class="about white-bg">
  <div class="container">
    <div class="row">
      <div class="col-md-5">
        <div class="screendump">
          <img src="images/about1.jpg">
        </div>
      </div>
      <div class="col-md-7">
        <h2>Live PDF Creation</h2>
        <p><strong>Look professional from day #1.</strong> Select one of our beautiful invoice templates to suit your company identity, switch between designs in real time to preview invoices & email them to clients with one click. The live preview PDF function was designed for an efficient and hassle-free experience, and it’s awesome!
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
        <p><strong>Authorize.net, Beanstream, PayPal?</strong> InvoiceNinja supports the most popular online payment gateways! If you need help integrating a third party gateway we don’t yet support, please contact us!  We’re happy to help!  If you need assistance of want to learn more about online payment solutions, contact us!</p>
      </div>
      <div class="col-md-5">
        <div class="screendump">
          <img src="images/about2.jpg">
        </div>
      </div>
    </div>
  </div>
</section>
<!--
<section class="about center white-bg">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Info about the company/story</h2>
        <p>Donec id elit non mi porta gravida at eget metus.
          Fusce dapibus, tellus ac cursus commodo, tortor mauris
          condimentum nibh, ut fermentum massa justo sit amet
          risus. Etiam porta sem malesuada magna mollis euismod.
          Donec sed odio dui.</p>
        </div>
      </div>
    </div>
  </section>
  -->
  <section class="upper-footer">
   <div class="container">
    <div class="row">
      <div class="col-md-3 center-block">
        <a href="#">
          <div class="cta">
            <h2 onclick="getStarted()">Invoice Now <span>+</span></h2>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>


@stop