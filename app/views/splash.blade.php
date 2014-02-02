@extends('master')

@section('head')    
<link href="{{ asset('css/bootstrap.splash.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/splash.css') }}" rel="stylesheet" type="text/css"/>    
<link href="{{ asset('images/apple-touch-icon-114x114-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="114x114">
<link href="{{ asset('images/apple-touch-icon-72x72-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="72x72">
<link href="{{ asset('images/apple-touch-icon-57x57-precomposed.png') }}" rel="apple-touch-icon-precomposed">
<link href='//fonts.googleapis.com/css?family=Roboto:400,700,900,100' rel='stylesheet' type='text/css'>
<link href='//fonts.googleapis.com/css?family=Roboto+Slab:400,300,700' rel='stylesheet' type='text/css'>
@stop

@section('body')

{{ Form::open(array('url' => 'get_started', 'id' => 'startForm')) }}
{{ Form::hidden('guest_key') }}
{{ Form::close() }}

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

    if (isStorageSupported()) {
      $('[name="guest_key"]').val(localStorage.getItem('guest_key'));          
    }
  });

  function isStorageSupported() {
    try {
      return 'localStorage' in window && window['localStorage'] !== null;
    } catch (e) {
      return false;
    }
  }

  function getStarted() {
    $('#startForm').submit();
  }

</script>    

<div class="navbar" style="margin-bottom:0px">
  <div class="container">
    <div class="navbar-inner">
      <a class="brand" href="#"><img src=
        "images/invoiceninja-logo.png"></a>
        <ul class="navbar-list">
          <li>{{ link_to('login', Auth::check() ? 'Continue' : 'Login' ) }}</li>
        </ul>
      </div>
    </div>
  </div>

  <section class="hero background" data-speed="2" data-type="background">
    <div class="caption-side"></div>

    <div class="container">
      <div class="row" style="margin:0;">
        <div class="caption-wrap">
          <div class="caption">
            <h1>THE <span style="color:#2299c0">SIMPLE</span> &amp;
              <span style="color:#edd71e">FREE</span> WAY TO INVOICE
              CLIENTS</h1>
              <p>It's that easy. Stop spending time on
                complicated and expensive invoicing.<br>
                No fuss, just get started and <span style=
                "color:#2299c0">get paid.</span></p>
              </div>
            </div>
          </div>
        </div>

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

      <section class="features">
        <div class="container">
          <div class="row">
            <div class="col-md-3 one">
              <div class="box">
                <div class="icon"><img src="images/icon-free.png"></div>
                <h2>100% FREE, ALWAYS</h2>
                <p>Invoicing with no monthly fee, because you have enough bills already! Free, now and forever! Quality invoicing to build your business and get paid.</p>
                </div>
              </div>

              <div class="col-md-3 two">
                <div class="box">
                  <div class="icon"><img src=
                    "images/icon-opensource.png"></div>
                    <h2>OPEN-SOURCE</h2>
                    <p>Cloud-based, super secure, and user-developed. Open source platforms are a better way to do business (and save the world). Need we say more?</p>
                    </div>
                  </div>

                  <div class="col-md-3 three">
                    <div class="box">
                      <div class="icon"><img src="images/icon-pdf.png"></div>
                      <h2>LIVE .PDF VIEW</h2>
                      <p>Create beautiful email-ready .PDF invoices created instantly as you type. Our ‘Save & send’ feature saves you time and impresses clients.</p>
                      </div>
                    </div>

                    <div class="col-md-3 four">
                      <div class="box">
                        <div class="icon"><img src=
                          "images/icon-payment.png"></div>
                          <h2>ONLINE PAYMENTS</h2>
                          <p>PayPal? Authorize.Net? Stripe? We support many payment technologies and if you need help or advise we’ll lend a hand (we’re pretty friendly).</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </section>

                  <section class="blue">
                    <div class="container">
                      <div class="row">
                        <div class="col-md-6">
                          <!--<h1>2.500 <span>sent invoices</span></h1>-->
                        </div>
                        <div class="col-md-6">
                          <!--<h1>$350.456 <span>billed</span></h1>-->
                        </div>
                      </div>
                    </div>
                  </section>

                  <section class="hero2">
                    <div class="container">
                      <div class="caption">
                        <h1>SIMPLE, INTUITIVE INVOICING</h1>
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
                  "http://twitter.com/invoiceninja" target="_blank"><span class=
                  "socicon">b</span></a> <a href=
                  "http://facebook.com/invoiceninja" target="_blank"><span class=
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