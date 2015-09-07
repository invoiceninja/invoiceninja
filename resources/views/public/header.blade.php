@extends('master')

@section('head')

<link href="{{ asset('css/built.public.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
<style type="text/css">


body {
    font-family: 'Roboto', sans-serif;
    font-size: 14px;
}


@media screen and (min-width: 700px) { 
    .navbar-header {
        padding-top: 16px;
        padding-bottom: 16px;        
    }
    .navbar li a {
        padding: 31px 20px 31px 20px;
    }
}

#footer {
    text-align: center
}

#footer .top {
    background: #2e2b2b;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
    padding: 40px 0 27px;
}

#footer .top li {
    display: inline-block;
    margin: 0 30px 10px;
}

#footer .top a {
    color: #fff;
    text-decoration: none;
}

#footer .bottom {
    border-top: 1px solid #5f5d5d;
    background: #211f1f;
    font-size: 11px;
    font-weight: 400;
    color: #636262;
    padding: 28px 0;
}

#footer .bottom a {
    color: #636262;
}

#footer .menu-item-31 a:before {
    content: '';
    display: inline-block;
    width: 9px;
    height: 15px;
    background: url({{ asset('images/social/facebook.svg') }}) no-repeat;
    margin: 0 6px 0 0;
    position: relative;
    top: 3px;
}

#footer .menu-item-32 a:before {
    content: '';
    display: inline-block;
    width: 19px;
    height: 16px;
    background: url({{ asset('images/social/twitter.svg') }}) no-repeat;
    margin: 0 6px 0 0;
    position: relative;
    top: 3px;
}

#footer .menu-item-33 a:before {
    content: '';
    display: inline-block;
    width: 19px;
    height: 16px;
    background: url({{ asset('images/social/github.png') }}) no-repeat;
    margin: 0 6px 0 0;
    position: relative;
    top: 3px;
}

/* Hide bootstrap sort header icons */
table.table thead .sorting:after { content: '' !important }
table.table thead .sorting_asc:after { content: '' !important }
table.table thead .sorting_desc:after { content: '' !important}
table.table thead .sorting_asc_disabled:after { content: '' !important }
table.table thead .sorting_desc_disabled:after { content: '' !important }

.dataTables_length {
    padding-left: 20px;
    padding-top: 8px;
}

.dataTables_length label {
    font-weight: 500;
}

@media screen and (min-width: 700px) { 
    #footer .top {
        padding: 27px 0;
    }

    #footer .bottom {
        padding: 25px 0;
    }
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

{!! Form::open(array('url' => 'get_started', 'id' => 'startForm')) !!}
{!! Form::hidden('guest_key') !!}
{!! Form::hidden('sign_up', Input::get('sign_up')) !!}
{!! Form::hidden('redirect_to', Input::get('redirect_to')) !!}
{!! Form::close() !!}

<script>
    if (isStorageSupported()) {
        $('[name="guest_key"]').val(localStorage.getItem('guest_key'));
    }

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


<nav class="navbar navbar-top navbar-inverse">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            @if (!isset($hideLogo) || !$hideLogo)
                {{-- Per our license, please do not remove or modify this link. --}}
                <a class="navbar-brand" href="{{ URL::to(NINJA_WEB_URL) }}" target="_blank"><img src="{{ asset('images/invoiceninja-logo.png') }}"></a>
            @endif            
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            @if (!isset($hideHeader) || !$hideHeader)
            <ul class="nav navbar-nav navbar-right">
                <li {{ Request::is('*client/quotes') ? 'class="active"' : '' }}>
                    {!! link_to('/client/quotes', trans('texts.quotes') ) !!}
                </li>
                <li {{ Request::is('*client/invoices') ? 'class="active"' : '' }}>
                    {!! link_to('/client/invoices', trans('texts.invoices') ) !!}
                </li>
                <li {{ Request::is('*client/payments') ? 'class="active"' : '' }}>
                    {!! link_to('/client/payments', trans('texts.payments') ) !!}
                </li>                
            </ul>
            @endif
        </div><!--/.nav-collapse -->
    </div>
</nav>


    <div class="container">
      @if (Session::has('warning'))
      <div class="alert alert-warning">{!! Session::get('warning') !!}</div>
      @endif

      @if (Session::has('message'))
      <div class="alert alert-info">{!! Session::get('message') !!}</div>
      @endif

      @if (Session::has('error'))
      <div class="alert alert-danger">{!! Session::get('error') !!}</div>
      @endif
  </div>

@yield('content')

<footer id="footer" role="contentinfo">
    <div class="top">
        <div class="wrap">
            @if (!isset($hideLogo) || !$hideLogo)                                    
            <div id="footer-menu" class="menu-wrap">
                <ul id="menu-footer-menu" class="menu">
                    <li id="menu-item-31" class="menu-item-31">                    
                        {!! link_to('#', 'Facebook', ['target' => '_blank', 'onclick' => 'openUrl("https://www.facebook.com/invoiceninja", "/footer/social/facebook")']) !!}
                    </li>
                    <li id="menu-item-32" class="menu-item-32">
                        {!! link_to('#', 'Twitter', ['target' => '_blank', 'onclick' => 'openUrl("https://twitter.com/invoiceninja", "/footer/social/twitter")']) !!}
                    </li>
                    <li id="menu-item-33" class="menu-item-33">
                        {!! link_to('#', 'GitHub', ['target' => '_blank', 'onclick' => 'openUrl("https://github.com/hillelcoren/invoice-ninja", "/footer/social/github")']) !!}
                    </li>                    
                    <li id="menu-item-30" class="menu-item-30">
                        {!! link_to(NINJA_WEB_URL . '/contact', trans('texts.contact')) !!}
                    </li>
                </ul>
            </div>      
            @endif   
        </div><!-- .wrap -->
    </div><!-- .top -->
    
    <div class="bottom">
        <div class="wrap">
            <div class="copy">Copyright &copy;2015 <a href="{{ NINJA_WEB_URL }}" target="_blank">Invoice Ninja</a>. All rights reserved.</div>
        </div><!-- .wrap -->
    </div><!-- .bottom -->
</footer><!-- #footer -->


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



  @stop
