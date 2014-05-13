@extends('master')

@section('head')    
<link href="{{ asset('built.public.css') }}" rel="stylesheet" type="text/css"/>

<!--
<link href="{{ asset('vendor/bootstrap/dist/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/bootstrap.splash.css') }}" rel="stylesheet" type="text/css"/> 
<link href="{{ asset('css/splash.css') }}" rel="stylesheet" type="text/css"/>  
-->  
<link href="{{ asset('css/splash.css') }}" rel="stylesheet" type="text/css"/>  
<!--
<link href="{{ asset('images/apple-touch-icon-114x114-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="114x114">
<link href="{{ asset('images/apple-touch-icon-72x72-precomposed.png') }}" rel="apple-touch-icon-precomposed" sizes="72x72">
<link href="{{ asset('images/apple-touch-icon-57x57-precomposed.png') }}" rel="apple-touch-icon-precomposed">
<!-- <script src="{{ asset('js/simpleexpand.js') }}" type="text/javascript"></script> -->

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script src="{{ asset('js/valign.js') }}" type="text/javascript"></script>

<style>
.hero {
    background-image: url({{ asset('/images/hero-bg-1.jpg') }});
}
.hero-about {
    background-image: url({{ asset('/images/hero-bg-3.jpg') }});
}
.hero-plans {
    background-image: url({{ asset('/images/hero-bg-plans.jpg') }});
}
.hero-contact {
    background-image: url({{ asset('/images/hero-bg-contact.jpg') }});
}
.hero-features {
    background-image: url({{ asset('/images/hero-bg-3.jpg') }});
}
.hero-secure {
    background-image: url({{ asset('/images/hero-bg-secure-pay.jpg') }});
}    
</style>

@stop

@section('body')

<!--
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=635126583203143";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
-->

{{ Form::open(array('url' => 'get_started', 'id' => 'startForm')) }}
{{ Form::hidden('guest_key') }}
{{ Form::close() }}

<script>
  if (isStorageSupported()) {
    $('[name="guest_key"]').val(localStorage.getItem('guest_key'));          
  }

  @if (isset($invoiceNow) && $invoiceNow)
    getStarted();
  @endif

  function isStorageSupported() {
    if ('localStorage' in window && window['localStorage'] !== null) {
      var storage = window.localStorage;
    } else {
      return false;
    }
    var testKey = 'test';
    try {
      storage.setItem(testKey, '1');
      storage.removeItem(testKey);
      return true;
    } catch (error) {
      return false;
    }    
  }

  function getStarted() {
    $('#startForm').submit();
    return false;
  }

</script>    

<div class="navbar" style="margin-bottom:0px">
  <div class="container">
    <div class="navbar-inner">
      <a class="brand" href="{{ URL::to('/') }}"><img src="{{ asset('images/invoiceninja-logo.png') }}"></a>
        <ul class="navbar-list">
          <!-- <li>{{ link_to('features', 'Features' ) }}</li> -->
          <!-- <li>{{ link_to('faq', 'FAQ' ) }}</li> -->
          <li>{{ link_to('about', 'About Us' ) }}</li>
          <li>{{ link_to('plans', 'Plans' ) }}</li>
          <li>{{ link_to('contact', 'Contact Us' ) }}</li>
          <li>{{ link_to('http://blog.invoiceninja.com', 'Blog' ) }}</li>
          <li>{{ link_to('login', Auth::check() ? 'My Account' : 'Login' ) }}</li>
        </ul>
      </div>
    </div>
  </div>


  @yield('content')   


  <footer class="footer">
      <div class="container">
        <div class="row">
            <div class="col-md-4">
          <!--<div class="fb-follow" data-href="https://www.facebook.com/invoiceninja" data-colorscheme="light" data-layout="button" data-show-faces="false"></div>-->
      
          <!--<a href="https://twitter.com/invoiceninja" class="twitter-follow-button" data-show-count="false" data-size="large">Follow @invoiceninja</a>
          <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>-->
          <!--<div class="fb-like" data-href="https://www.invoiceninja.com" data-layout="button" data-action="like" data-show-faces="false" data-share="false"></div>          -->
          <!--
          <div class="fb-share-button" data-href="https://www.invoiceninja.com/" data-type="button"></div>
          &nbsp;

          <a href="https://twitter.com/share" class="twitter-share-button" data-url="https://www.invoiceninja.com/" data-via="invoiceninja" data-related="hillelcoren" data-count="none" data-text="Free online invoicing">Tweet</a>
          <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
          &nbsp;
          <div class="g-plusone" data-size="medium" data-width="300" data-href="https://www.invoiceninja.com/" data-annotation="none" data-count="false" data-recommendations="false"></div>

          <script type="text/javascript">
            (function() {
              var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
              po.src = 'https://apis.google.com/js/platform.js';
              var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
            })();
          </script>       
          &nbsp;
          -->

          <!--
          <script src="//platform.linkedin.com/in.js" type="text/javascript">
            lang: en_US
          </script>
          <script type="IN/Share" data-url="https://www.invoiceninja.com/"></script>
          -->
                    
          <!--<iframe src="http://ghbtns.com/github-btn.html?user=hillelcoren&repo=invoice-ninja&type=watch" allowtransparency="true" frameborder="0" scrolling="0" width="62" height="20"></iframe>-->

  <img src="{{ asset('images/footer-logo.png') }}">
                	<hr>
  <ul class="navbar-vertical">
    <!-- <li>{{ link_to('features', 'Features' ) }}</li> -->
    <!-- <li>{{ link_to('faq', 'FAQ' ) }}</li> -->
    <li>{{ link_to('about', 'About Us' ) }}</li>
    <li>{{ link_to('plans', 'Plans' ) }}</li>
    <li>{{ link_to('contact', 'Contact Us' ) }}</li>
    <li>{{ link_to('http://blog.invoiceninja.com', 'Blog' ) }}</li>
    <li>{{ link_to('login', Auth::check() ? 'My Account' : 'Login' ) }}</li>
  </ul>
</div> 
            
            <div class="col-md-4">
                <h3><span class="glyphicon glyphicon-share-alt"></span>Connect with Us</h3>
                <div class="social">
                <div class="row1">
                    <a href="https://www.facebook.com/invoiceninja"><img src="{{ asset('images/hex-facebook.png') }}"></a>
                    <a href="https://www.facebook.com/invoiceninja"><img src="{{ asset('images/hex-twitter.png') }}"></a>
                    <a href="https://www.facebook.com/invoiceninja"><img src="{{ asset('images/hex-linkedin.png') }}"></a>
                    <a href="https://www.facebook.com/invoiceninja"><img src="{{ asset('images/hex-gplus.png') }}"></a>
                    </div>
                <div class="row2">
                    <a href="https://www.facebook.com/invoiceninja"><img src="{{ asset('images/hex-github.png') }}"></a>
                    <a href="https://www.facebook.com/invoiceninja"><img src="{{ asset('images/hex-pinterest.png') }}"></a>
                    <a href="https://www.facebook.com/invoiceninja"><img src="{{ asset('images/hex-rss.png') }}"></a>
                   
                    </div>
                </div>
                <h3><span class="glyphicon glyphicon-envelope"></span>Join Our Free Newsletter</h3>
                <form id="newsletter">
                <div class="form-group">
                    <input type="email" class="form-control" id="email" name="email" placeholder="Email Address">
                    {{ Button::submit('')->append_with_icon('chevron-right') }}
                    <span class="help-block" style="display: none;">Please enter a valid e-mail address.</span>
            
                </div>
                </form>
            
            </div>
            
            <div class="col-md-4">
                <h3><img src="{{ asset('images/icon-secure-footer.png') }}" style="margin-right: 8px; margin-top: -5px;"></span>Safe & Secure</h3>
            <img src="{{ asset('images/ssl-footer.png') }}">
            <hr>
            <img src="{{ asset('images/opensource-footer.png') }}">
                </div>

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
</div>
</footer>

<script type="text/javascript">

jQuery(document).ready(function($) {   
	$('.valign').vAlign();  
});

</script>

<!--
All images in the site need to have retina versions otherwise the log fills up with requests for missing files
<script src="{{ asset('/js/retina-1.1.0.min.js') }}" type="text/javascript"></script>
-->


@stop