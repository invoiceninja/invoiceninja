@extends('master')

@section('head')    
<link href="{{ asset('css/bootstrap.splash.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/splash.css') }}" rel="stylesheet" type="text/css"/>    
<link href="{{ asset('images/apple-touch-icon-114x114-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="114x114">
<link href="{{ asset('images/apple-touch-icon-72x72-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="72x72">
<link href="{{ asset('images/apple-touch-icon-57x57-precomposed.png') }}" rel="apple-touch-icon-precomposed">
@stop

@section('body')

  <div class="navbar" style="margin-bottom:0px">
    <div class="container">
      <div class="navbar-inner">
        <a class="brand" href="#"><img src=
          "images/invoiceninja-logo.png"></a>
          <ul class="navbar-list">
            <li>{{ link_to('about', 'About Us' ) }}</li>
            <li>{{ link_to('contact', 'Contact Us' ) }}</li>
            <li>{{ link_to('login', Auth::check() ? 'My Account' : 'Login' ) }}</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <section class="hero3" data-speed="2" data-type="background">
    <div class="container">
      <div class="caption">
       <h1>Terms of Service & Conditions of Use
       </h1>
     </div>
   </div>
 </section>

  <section class="about center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <p>Invoice Ninja LLC provides this website and services under the following terms of service and conditions of use. By utilizing the invoiceNinja.com website you are agreeing to the following terms of service & conditions of use. You must be of legal age of majority to enter into a binding agreement to use invoiceninja.com. If you do not agree to the below terms & conditions, do not use invoiceninja.com. </p>
      </div>
    </div>
  </div>
  </section>




 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Definitions</h2>
        <p>Invoiceninja.com users who access invoiceninja.com services are defined as “User Accounts.” User Account clients who use access invoiceninja.com services to view and/or pay invoices are defined as “Clients.” The wording “data” and “content” are used interchangeably. </p></div>
    </div>
  </div>
  </section>

 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Responsibility</h2>
        <p>User Accounts must ensure the confidentiality of usernames and passwords used to access their account. User Accounts are responsible for all activity occurring under their account including all laws relating to data, privacy, personal information, international copyright and trademark laws.</p>
      </div>
    </div>
  </div>
  </section>

 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Data Ownership</h2>
        <p>The User Accounts owns all data generated in their invoiceninja.com account. Invoiceninja.com will not modify or distribute User Account data. Invoiceninja.com will store and access data solely for the purpose of providing services to User Accounts.</p>
        <p>User Accounts are responsible for their data. Invoiceninja.com has no responsibility or liability for User Account data or Client experience. User Accounts are responsible for any loss or damage a User Account may cause to their Clients or other people. Although we have no obligation to do so, if deemed legally necessary invoiceninja.com has absolute discretion to remove or edit User Account data without notice or penalty.</p>
        <p>Invoiceninja.com does not own User Account data, but we do reserve the right to use User Account data as necessary to operate invoiceninja.com and improve User Account services.</p>
      </div>
    </div>
  </div>
  </section>


 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Copyright & Trademarks</h2>
        <p>User Accounts are responsible that company logos, graphics, and content uploaded to invoiceninja.com do not infringe on international copyright & trademark law.</p>
      </div>
    </div>
  </div>
  </section>


 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Open Source License</h2>
        <p>Invoiceninja.com is an open source application and invoiceninja.com source code is governed by international attribution assurances licensing: <a href="https://github.com/hillelcoren/invoice-ninja/blob/master/LICENSE" target="_blank">https://github.com/hillelcoren/invoice-ninja/blob/master/LICENSE</a>.</p>
      </div>
    </div>
  </div>
  </section>


 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>User Account Limited License </h2>
        <p>Invoiceninja.com grants User Accounts & Clients a limited license to access the invoiceninja.com services such as User Account creation and all invoiceninja.om services, and Client services such as viewing invoices, downloading invoices, and printing invoices. This limited license may be revoked if deemed legally necessary without notice or penalty.</p>
      </div>
    </div>
  </div>
  </section>


 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Use of Emailing Services</h2>
        <p>Keep it legal and clean. Any User Account emailing invoices data, hyper-links, or other material that is unlawful, libelous, defamatory, pornographic, harassing, invasive, fraudulent or otherwise objectionable will be deactivated.</p>
        <p>Content that would constitute criminal offence or create legal liability, violate copyright, trademark, or intellectual property will be deleted or provided to legal authorities.</p>
      </div>
    </div>
  </div>
  </section>


 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Security</h2>
        <p>Invoiceninja.com does not store or obtain credit card or sensitive financial data in any form. Responsibility for Third-Party Material User Account may utilize hyper-linking to third-party web sites. Invoiceninja.com takes no responsibility for third party content.</p>
      </div>
    </div>
  </div>
  </section>


 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Limited Liability</h2>
        <p>User Accounts and Clients agree to indemnify, defend, and hold invoiceninja.com, its directors or employees harmless against any and all liability and cost as a result of using invoiceninja.com. User Accounts and Clients shall not assert any claim or allegation of any nature whatsoever against invoiceninja.com, its directors or employees. </p>
        <p>Invoiceninja.com shall not be liable for damages of any kind, including but not limited to loss of site use, loss of profits or loss of data, tort or otherwise, arising out of or in any way connected with the use of or inability to use invoiceninja.com.</p>
        <p>You shall defend, indemnify and hold harmless invoiceninja.com from any loss, damages, liabilities, expenses, claims and proceedings arising out of your use of invoiceninja.com.</p>
      </div>
    </div>
  </div>
  </section>

 <section class="center">
  <div class="container">
    <div class="row">
      <div class="col-md-8 col-md-offset-2">
        <h2>Availability</h2>
        <p>Invoiceninja.com uses third party hosting that strives to ensure maximum uptime. However, invoiceninja.com cannot guarantee uninterrupted access invoiceninja.com. Invoiceninja.com reserves the right to interrupt access to invoiceninja.com for the sake of forming maintenance, updates, and security requirements.</p>
      </div>
    </div>
  </div>
  </section>

  <p>&nbsp;</p>
  <p>&nbsp;</p>


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
                    <li>{{ link_to('about', 'About Us' ) }}</li>
                    <li>{{ link_to('contact', 'Contact Us' ) }}</li>
                    <li>{{ link_to('login', Auth::check() ? 'My Account' : 'Login' ) }}</li>
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