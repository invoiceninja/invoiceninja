@extends('public.header')


@section('content')

<script>
  $(document).ready(function () {

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

</script>

@section('content')

<section class="hero background hero4" data-speed="2" data-type="background">
  <div class="caption-side"></div>
  <div class="container">
    <div class="row" style="margin:0;">
      <div class="caption-wrap">
        <div class="caption">
          <h1>Contact<span style="color:#ecd816"> us</span></h1>
            </div>
          </div>
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
          <h2>Questions, special requests, or just want to say hi?</h2>
          <p>Fill in the form below and we'll get back to you as soon as possible. Hope to hear from you!</p>
          
          {{ Form::open(['url' => 'contact', 'class' => 'feedbackForm']) }}
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

          {{ Form::close() }}
          
        </div>
        <div class="col-md-4 col-md-offset-1 address">
          <h2>Other ways to reach us</h2>
          <p><span class="glyphicon glyphicon-send"></span><a href="mailto:contact@invoiceninja.com">contact@invoiceninja.com</a></p>
          <p><span class="glyphicon glyphicon-earphone"></span>+1-800-763-1948</p>
          <p><span class="glyphicon glyphicon-comment"></span><a href="http://www.invoiceninja.org" target="_blank">Google Group</a></p>        
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
          <h2 onclick="return getStarted()">Invoice Now <span>+</span></h2>
        </div>
      </a>
    </div>
  </div>
</div>
</section>

@stop