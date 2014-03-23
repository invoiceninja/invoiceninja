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

<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=635126583203143";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>


{{ Form::open(array('url' => 'get_started', 'id' => 'startForm')) }}
{{ Form::hidden('guest_key') }}
{{ Form::close() }}


<script>
  $(document).ready(function () {      
    if (isStorageSupported()) {
      $('[name="guest_key"]').val(localStorage.getItem('guest_key'));          
    }
  });

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


  @yield('content')   



  <footer>
    <div class="navbar" style="margin-bottom:0px">
      <div class="container">
        <div class="social">
          <!--<div class="fb-follow" data-href="https://www.facebook.com/invoiceninja" data-colorscheme="light" data-layout="button" data-show-faces="false"></div>-->
      
          <!--<a href="https://twitter.com/invoiceninja" class="twitter-follow-button" data-show-count="false" data-size="large">Follow @invoiceninja</a>
          <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>-->
          <div class="fb-like" data-href="https://www.invoiceninja.com" data-layout="button" data-action="like" data-show-faces="false" data-share="false"></div>          
          &nbsp;

          <a href="https://twitter.com/share" class="twitter-share-button" data-url="https://www.invoiceninja.com/" data-via="invoiceninja" data-related="hillelcoren" data-count="none" data-text="Free online invoicing">Tweet</a>
          <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
          &nbsp;
          <!-- Place this tag where you want the +1 button to render. -->
          <div class="g-plusone" data-size="medium" data-width="300" data-href="https://www.invoiceninja.com/" data-annotation="none" data-count="false" data-recommendations="false"></div>

          <!-- Place this tag after the last +1 button tag. -->
          <script type="text/javascript">
            (function() {
              var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
              po.src = 'https://apis.google.com/js/platform.js';
              var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
            })();
          </script>       
          &nbsp;

          <!--<iframe src="http://ghbtns.com/github-btn.html?user=hillelcoren&repo=invoice-ninja&type=watch" allowtransparency="true" frameborder="0" scrolling="0" width="62" height="20"></iframe>-->

  <p>&nbsp;</p>
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