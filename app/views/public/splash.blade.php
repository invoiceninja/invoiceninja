@extends('public.header')

@section('content') 


<section class="hero background hero1" data-speed="2" data-type="background">
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
                <h2 id="startButton" onclick="return getStarted()">Invoice Now <span>+</span></h2>
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
              <div class="icon"><img src="{{ asset('images/icon-free.png') }}"></div>
              <h2>FREE, ALWAYS</h2>
              <p>Invoicing with no monthly fee, because you have enough bills already! Free, now and forever! Quality invoicing to build your business and get paid.</p>
            </div>
          </div>

          <div class="col-md-3 two">
            <div class="box">
              <div class="icon"><img src="{{ asset('images/icon-opensource.png') }}"></div>
                <h2>OPEN-SOURCE</h2>
                <p>Cloud-based, super secure, and user-developed. Open source platforms are a better way to do business (and save the world). Need we say more?</p>
              </div>
            </div>

            <div class="col-md-3 three">
              <div class="box">
                <div class="icon"><img src="{{ asset('images/icon-pdf.png') }}"></div>
                <h2>LIVE .PDF VIEW</h2>
                <p>Create beautiful email-ready .PDF invoices created instantly as you type. Our ‘Save & send’ feature saves you time and impresses clients.</p>
              </div>
            </div>

            <div class="col-md-3 four">
              <div class="box">
                <div class="icon"><img src="{{ asset('images/icon-payment.png') }}"></div>
                  <h2>ONLINE PAYMENTS</h2>
                  <p>PayPal? Authorize.Net? Stripe? We support many payment technologies and if you need help or advice we’ll lend a hand (we’re pretty friendly).</p>
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
              <h1>SIMPLE, INTUITIVE INVOICING.</h1>
            </div>
          </div>
        </section>

        <section class="upper-footer">
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