@extends('public.header')

@section('content')

  <section class="hero background hero3" data-speed="2" data-type="background">
  <div class="caption-side"></div>
  <div class="container">
    <div class="row" style="margin:0;">
      <div class="caption-wrap">
        <div class="caption">
          <h1>The <span style="color:#ecd816"> Plans</span></h1>
            </div>
          </div>
        </div>
      </div>
    </section>

 <section class="plans center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Go Pro to Unlock Premium Invoice Ninja Features</h2>
        <p>We believe that the free version of Invoice Ninja is a truly awesome product loaded 
          with the key features you need to bill your clients electronically. But for those who 
          crave still more Ninja awesomeness, we've unmasked the Invoice Ninja Pro plan, which 
          offers more versatility, power and customization options for just $50 per year. </p>
      </div>
    </div>
  </div>
 <div class="container">
      <div class="row">
        <div class="plans-table col-md-9">
        <div class="col-md-4 desc hide-mobile">
            <div class="cell"></div>
            <div class="cell">Number of clients per account</div>
            <div class="cell">Unlimited client invoices</div>
            <div class="cell">Add your company logo</div>
            <div class="cell">Live .PDF invoice creation </div>
            <div class="cell">4 beatiful invoice templates</div>
            <div class="cell">Accept credit card payments</div>
            <div class="cell">Custom invoice fields</div>
            <div class="cell">Priority email support</div>
            <div class="cell">Custom invoice colors</div>
            <div class="cell">Remove "Created by Invoice Ninja"</div>
                        <div class="cell">Pricing</div>

            
            </div>
        <div class="free col-md-4">
            <div class="cell">Free</div>
            <div class="cell"><div class="hide-desktop">Number of clients per account</div><span>500</span></div>
            <div class="cell"><div class="hide-desktop">Unlimited client invoices</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Add your company logo</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Live .PDF invoice creation</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">4 beatiful invoice templates</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Accept credit card payments</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Custom invoice fields</div><span class="glyphicon glyphicon-remove"></div>
            <div class="cell"><div class="hide-desktop">Priority email support</div><span class="glyphicon glyphicon-remove"></div>
            <div class="cell"><div class="hide-desktop">Custom invoice colors</div><span class="glyphicon glyphicon-remove"></div>
            <div class="cell"><div class="hide-desktop">Remove "Created by Invoice Ninja"</div><span class="glyphicon glyphicon-remove"></div>
            <div class="cell price"><div class="hide-desktop">Pricing</div><p>Free<span> /Always!</span></p></div>
            </div>
        <div class="pro col-md-4">
            
            <div class="cell">Pro Plan<span class="glyphicon glyphicon-star"></div>
            <div class="cell"><div class="hide-desktop">Number of clients per account</div><span style="color: #2299c0; font-size: 16px;">5,000</span></div>
            <div class="cell"><div class="hide-desktop">Unlimited client invoices</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Add your company logo</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Live .PDF invoice creation</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">4 beatiful invoice templates</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Accept credit card payments</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Custom invoice fields</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Priority email support</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Custom invoice colors</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell"><div class="hide-desktop">Remove "Created by Invoice Ninja"</div><span class="glyphicon glyphicon-ok"></div>
            <div class="cell price"><div class="hide-desktop">Pricing</div><p>$50<span> /Year</span></p></div>
          <!--
            <div class="cell">
            <a href="#">
        <div class="cta">
          <h2 onclick="return getStarted()">GO PRO <span>+</span></h2>
        </div>
        -->
      </a>
            </div>
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