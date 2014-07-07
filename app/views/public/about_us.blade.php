@extends('public.header')

@section('content')
<section class="hero background hero-about" data-speed="2" data-type="background">
    <div class="container">
        <div class="row">
            <h1><img src="{{ asset('images/icon-about.png') }}"><span class="thin">About</span> Invoice Ninja</h1>
        </div>
    </div>
</section>

<section class="about">
    <div class="container">
        <div class="row">
            <div class="col-md-5 valign">          
                <div class="headline">
                    <h2>What is Invoice Ninja?</h2>
                </div>
                <p class="first">Invoice Ninja is a free, open-source solution for invoicing and billing customers. With Invoice Ninja, you can easily build and send beautiful invoices from any device that has access to the web. Your clients can print your invoices, download them as pdf files, and even pay you online from within the system.</p>
            </div>
            <div class="col-md-7">
                <img src="{{ asset('images/devices3.png') }}">
            </div>
        </div>
    </div>
</section>

<section class="team center">
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <h2>Team Ninja</h2>
                    <p>Invoice Ninja is managed by a team of seasoned web workers. We 
                    launched in early 2014 and have been thrilled by the enthusiastic response we’ve 
                    received from our growing community of users.</p>
            </div>
          </div>
        <div class="row">
            <div class="col-md-3">
                <div class="img-team">
                    <img src="images/shalomstark.jpg" alt="Shalom Stark">
                </div>
                <h2>Shalom Stark</h2>
                <p>Co-Founder, CEO</p>
                <p class="social blue"><a href="https://twitter.com/shalomstark" target="_blank"><img src="images/twitter.svg" alt="Twitter"></a>
                    <a href="http://shalomisraeltours.com/" target="_blank"><img src="images/website.svg" alt="Website"></a>
                </p>
                <p>Shalom has specialized in small business development for nearly 10 years.  In addition to InvoiceNinja.com Shalom is CEO of a leading                    tour agency in Israel.</p>
            </div>
            <div class="col-md-3">
                <div class="img-team">
                    <img src="images/hillelcoren.jpg" alt="Hillel Coren">
                </div>
                <h2>Hillel Coren</h2>
                <p>Co-Founder, CTO</p>
                <p class="social green"><a href="https://twitter.com/hillelcoren" target="_blank"><img src="images/twitter.svg" alt="Twitter"></a>
                    <a href="http://www.linkedin.com/profile/view?id=105143214" target="_blank"><img src="images/linkedin.svg" alt=""></a>
                    <a href="http://hillelcoren.com/" target="_blank"><img src="images/website.svg" alt="Website"></a>
                </p>
                <p>Hillel has been developing enterprise applications for 15 years. His open-source <a  href="http://hillelcoren.com/flex-autocomplete/" target="_blank">AutoComplete</a> component has been used by  thousands of developers around the world.</p>
            </div>
            <div class="col-md-3">
                <div class="img-team">
                    <img src="images/razikantorp.jpg" alt="Razi Kantorp">
                </div>
                <h2>Razi Kantorp-Weglin</h2>
                <p>Designer</p>
                <p class="social red"><a href="https://twitter.com/kantorpweglin" target="_blank"><img src="images/twitter.svg" alt="Twitter"></a>
                    <a href="https://www.linkedin.com/pub/razi-kantorp/35/368/973" target="_blank"><img src="images/linkedin.svg" alt=""></a>
                    <a href="http://instagram.com/kantorpweglin" target="_blank"><img src="images/instagram.svg" alt="Twitter"></a>
                    <a href="http://kantorp-wegl.in/" target="_blank"><img src="images/website.svg" alt="Website"></a>
                </p>
                <p>Razi is a pixel nerd with a great deal of experience in design for web sites and applications. When she isn't busy with InvoiceNinja she runs a small web agency in Stockholm called kantorp-wegl.in</p>
            </div>
            <div class="col-md-3">
                <div class="img-team">
                    <img src="images/benjacobson.jpg" alt="Ben Jacobsen">
                </div>
                <h2>Ben Jacobson</h2>
                <p>Marketing</p>
                <p class="social yellow"><a href="https://twitter.com/osbennn" target="_blank"><img src="images/twitter.svg" alt="Twitter"></a>
                    <a href="http://www.linkedin.com/in/osbennn" target="_blank"><img src="images/linkedin.svg" alt=""></a>
                    <a href="http://about.me/osbennn" target="_blank"><img src="images/me.svg" alt="Me"></a>
                    <a href="http://actionpackedmedia.com/" target="_blank"><img src="images/website.svg" alt="Website"></a>
                </p>
                <p>A veteran digital marketer and content strategist, Ben specializes in building communities around brands that make business easier for freelancers, SMBs and micro-entrepreneurs.
                </p>
            </div>
        </div>
    </div>
</section>

@stop