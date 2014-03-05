@extends('master')

@section('head')    
<link href="{{ asset('css/bootstrap.splash.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/splash.css') }}" rel="stylesheet" type="text/css"/>    
<link href="{{ asset('images/apple-touch-icon-114x114-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="114x114">
<link href="{{ asset('images/apple-touch-icon-72x72-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="72x72">
<link href="{{ asset('images/apple-touch-icon-57x57-precomposed.png') }}" rel="apple-touch-icon-precomposed">
@stop

@section('body')
 <script>
        $(document).ready(function () {
    var $window = $(window);
    $('section[data-type="background"]').each(function () {
        var $bgobj = $(this);
        $(window).scroll(function () {
            var yPos = -($window.scrollTop() / $bgobj.data('speed'));
            var coords = '50% ' + yPos + 'px';
            $bgobj.css({ backgroundPosition: coords });
        });
    });
    });
</script>
<div class="navbar" style="margin-bottom:0px">
  <div class="container">
    <div class="navbar-inner">
      <a class="brand" href="#"><img src=
        "images/invoiceninja-logo.png"></a>
        <ul class="navbar-list">
          <li>{{ link_to('about_us', 'About Us' ) }}</li>
          <li>{{ link_to('contact_us', 'Contact Us' ) }}</li>
          <li>{{ link_to('login', Auth::check() ? 'Continue' : 'Login' ) }}</li>
        </ul>
      </div>
    </div>
  </div>
</div>

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
                        <p>Free yourself from online invoicing platforms with high monthly fees and limited functionality.  Being open source allows us fast app development, security audits by the open-course community, and we can keep it <span style="color:#2299c0"><strong>FREE!</strong></span></p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="about white-bg">
        <div class="container">
            <div class="row">
                <div class="col-md-5">
                    <div class="screendump">
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
                    </div>
                </div>
            </div>
        </div>
    </section>
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

                  <footer>
                    <div class="navbar" style="margin-bottom:0px">
                      <div class="container">
                        <div class="social">
                    <!--
                    <a href="http://twitter.com/eas_id"><span class=
                    "socicon">c</span></a> 
                  -->
                  <a href=
                  "http://facebook.com/invoiceninja" target="_blank"><span class=
                  "socicon">b</span></a> <a href=
                  "http://twitter.com/invoiceninja" target="_blank"><span class=
                  "socicon">a</span></a>
                  <p>Copyright © 2014 InvoiceNinja. All rights reserved.</p>
                </div>

                <div class="navbar-inner">
                  <ul class="navbar-list">
                    <li>{{ link_to('login', Auth::check() ? 'Continue' : 'Login' ) }}</li>
                  </ul>

                    <!--
                    <ul class="navbar-list">
                        <li><a href="#">For developers</a></li>
                        <li><a href="#">Jobs</a></li>
                        <li><a href="#">Terms &amp; Conditions</a></li>
                        <li><a href="#">Our Blog</a></li>
                    </ul>
                  -->
                </div>
              </div>
            </div>
          </footer><script src="{{ asset('/js/retina-1.1.0.min.js') }}" type="text/javascript"></script>

@stop