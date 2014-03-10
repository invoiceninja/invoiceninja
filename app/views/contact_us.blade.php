@extends('master')

@section('head') 
<link href="{{ asset('vendor/bootstrap/dist/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/bootstrap.splash.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/splash.css') }}" rel="stylesheet" type="text/css"/>    
<link href="{{ asset('images/apple-touch-icon-114x114-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="114x114">
<link href="{{ asset('images/apple-touch-icon-72x72-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="72x72">
<link href="{{ asset('images/apple-touch-icon-57x57-precomposed.png') }}" rel="apple-touch-icon-precomposed">
@stop

@section('body')

{{ Form::open(array('url' => 'get_started', 'id' => 'startForm')) }}
{{ Form::hidden('guest_key') }}
{{ Form::close() }}

 <script>
    $(document).ready(function () {

      if (isStorageSupported()) {
        $('[name="guest_key"]').val(localStorage.getItem('guest_key'));          
      }

        $("#feedbackSubmit").click(function() {
      //clear any errors
      contactForm.clearErrors();
   
      //do a little client-side validation -- check that each field has a value and e-mail field is in proper format
      var hasErrors = false;
      $('.feedbackForm input,textarea').each(function() {
        if (!$(this).val()) {
          hasErrors = true;
          contactForm.addError($(this));
        }
      });
      var $email = $('#email');
      if (!contactForm.isValidEmail($email.val())) {
        hasErrors = true;
        contactForm.addError($email);
      }
   
      //if there are any errors return without sending e-mail
      if (hasErrors) {
        return false;
      }
 
  }); 
    
});
 
//namespace as not to pollute global namespace
var contactForm = {
  isValidEmail: function (email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
  },
  clearErrors: function () {
    $('#emailAlert').remove();
    $('.feedbackForm .help-block').hide();
    $('.feedbackForm .form-group').removeClass('has-error');
  },
  addError: function ($input) {
    $input.siblings('.help-block').show();
    $input.parent('.form-group').addClass('has-error');
  },
  addAjaxMessage: function(msg, isError) {
    $("#feedbackSubmit").after('<div id="emailAlert" class="alert alert-' + (isError ? 'danger' : 'success') + '" style="margin-top: 5px;">' + $('<div/>').text(msg).html() + '</div>');
  }
    };

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
      <a class="brand" href="/"><img src=
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

    <section class="hero4" data-speed="2" data-type="background">
        <div class="container">
            <div class="caption">
                 <h1>Contact us
                 </h1>
            </div>
        </div>
    </section>

    <section class="about contact">
        <div class="container">
            <div id="contact_form" class="row">


                @if (Session::has('message'))
                  <div class="alert alert-info">{{ Session::get('message') }}</div>
                @endif

                @if (Session::has('error'))
                  <div class="alert alert-danger">{{ Session::get('error') }}</div>
                @endif

                     
                <div class="row">              
                    <div class="col-md-7">
                        <h2>Have a question or just want to say hi?</h2>
                        <p>Fill in the form below and we'll get back to you as soon as possible (within 24 hours). Hope to hear from you.</p>
                                                
                        {{ Former::open('contact')->addClass('feedbackForm') }}
                            <div class="form-group">
                                <input type="text" class="form-control" id="name" name="name" placeholder="Name">
                                <span class="help-block" style="display: none;">Please enter your name.</span>
                            </div>
                            <div class="form-group">
                                <input type="email" class="form-control" id="email" name="email" placeholder="Email Address">
                                <span class="help-block" style="display: none;">Please enter a valid e-mail address.</span>
                            </div>
                            <div class="form-group">
                                <textarea rows="10" cols="100" class="form-control" id="message" name="message" placeholder="Message"></textarea>
                                <span class="help-block" style="display: none;">Please enter a message.</span>
                            </div>
                            <div class="row">
                                <div class="col-md-5">
                                    <button type="submit" id="feedbackSubmit" class="btn btn-primary btn-lg">Send Message <span class="glyphicon glyphicon-send"></span></button>
                                </div>
                            </div>

                        {{ Former::close() }}
                        
                    </div>
                    <div class="col-md-4 col-md-offset-1 address">
                        <h2>Other ways to reach us</h2>
                        <p><span class="glyphicon glyphicon-send"></span><a href="mailto:contact@invoiceninja.com">contact@invoiceninja.com</a></p>
                        <p><span class="glyphicon glyphicon-earphone"></span>(800) 763-1948</p>
                        <p><span class="github"></span><div style="padding-top:10px"> &nbsp;&nbsp;<a href="https://github.com/hillelcoren/invoice-ninja" target="_blank">GitHub Project</a></div></p>

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