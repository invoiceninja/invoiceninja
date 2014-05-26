@extends('public.header')

@section('content') 



<section class="hero background hero1 center" data-speed="2" data-type="background">
  <div class="caption-side"></div>

  <div class="container">
      
        <div class="container">
            <div class="row" style="margin:0;">
                <div class="caption-wrap">
                    <div class="caption">
                        <h1>THE <span style="color:#2299c0">SIMPLE</span> &amp;
                        <span style="color:#edd71e">FREE</span> WAY TO INVOICE
                        CLIENTS</h1>
                        <p>It's just that easy. Stop spending time on
                        complicated and expensive invoicing.<br>
                        No fuss, just get started and <span style=
                        "xcolor:#2299c0">get paid.</span></p>
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

    <section class="features-splash">
      <div class="container">
        <div class="row">
          <div class="col-md-3 one">
            <div class="box">
              <div class="icon free"><span class="img-wrap"><img src="{{ asset('images/icon-free.png') }}"></span></div>
              <h2>Free, Always</h2>
              <!--<p>Invoicing with no monthly fee, because you have enough bills already! Free, now and forever! Quality invoicing to build your business and get paid.</p>-->
              <p>Send unlimited invoices to 500 clients per month and never pay a dime. You are welcome to unlock still more awesome features with our Pro Plan, but our free app is a top-notch product that will do everything you need it to do, without any subscription or fees.</p>              
            </div>
          </div>

          <div class="col-md-3 two">
            <div class="box">
              <div class="icon open"><span class="img-wrap"><img src="{{ asset('images/icon-opensource.png') }}"></span></div>
                <h2>Open-Source</h2>
                <!--<p>Cloud-based, super secure, and user-developed. Open source platforms are a better way to do business (and save the world). Need we say more?</p>-->
                <p>No mysterious corporate silos here! Just full <a href="https://github.com/hillelcoren/invoice-ninja" target="_blank">source code</a> transparency and a devotion to working with anyone interested to build a better electronic invoicing platform. We even offer a handy <a href="http://hillelcoren.com/invoice-ninja/self-hosting/" target="_blank">zip download</a> for a self-hosted version of Invoice Ninja.</p>                
              </div>
            </div>

            <div class="col-md-3 three">
              <div class="box">
                <div class="icon pdf"><span class="img-wrap"><img src="{{ asset('images/icon-pdf.png') }}"></span></div>
                <h2>Live .PDF View</h2>
                <!--<p>Create beautiful email-ready .PDF invoices created instantly as you type. Our ‘Save & send’ feature saves you time and impresses clients.</p>-->
                <p>See how your edited invoice will look as a print-friendly pdf while you make the changes. Our pdf generator works in real time as you make your changes. You can even preview four beautiful preset designs. Just create, save, send, and you’re done!</p>
              </div>
            </div>

            <div class="col-md-3 four">
              <div class="box">
                <div class="icon pay"><span class="img-wrap"><img src="{{ asset('images/icon-payment.png') }}"></span></div>
                  <h2>Online Payments</h2>
                  <p>Invoices sent with our app integrate seamlessly with the gateway credit card processor of your choice, to make it super easy for your clients to send you money with just a few clicks. We play nicely with Authorize.Net, Stripe, PayPal and loads more - 23 in all!</p>
                  <!--<p>PayPal? Authorize.Net? Stripe? We support many payment technologies and if you need help or advice we’ll lend a hand (we’re pretty friendly).</p>-->
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
               <h1><span>Simple, Intuitive Invoicing,</span>AnyWHERE.</h1>
                  <div class="row">
                  <div class="col-md-7">
                      <a href="#">
                          <div class="cta">
                              <h2 onclick="return getStarted()">Invoice Now <span>+</span></h2>
                        </div>
                      </a>

                      </div>
                      </div>
                  <!-- <p>or {{ link_to('features', 'View Our Features' ) }}</a></p> -->
                   <p>No signup needed</p>
              </div>
              <div class="col-md-7">
                <img src="{{ asset('images/devices.png') }}">
              </div>
            </div>
          </div>
        </section>


        @stop