@extends('master')

@section('head')
    @if (!empty($clientFontUrl))
        <link href="{!! $clientFontUrl !!}" rel="stylesheet" type="text/css">
    @endif
    <link href="{{ asset('css/built.public.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <style type="text/css">{!! isset($account)?$account->clientViewCSS():'' !!}</style>
@stop

@section('body')

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

  $(function() {
      function positionFooter() {
          // check that the footer appears at the bottom of the screen
          var height = $(window).height() - ($('#header').height() + $('#footer').height());
          if ($('#mainContent').height() < height) {
              $('#mainContent').css('min-height', height);
          }
      }

      positionFooter();
      $(window).resize(positionFooter);
  })

</script>


<div id="header">
    <nav class="navbar navbar-top navbar-inverse">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                @if (!isset($account) || !$account->hasFeature(FEATURE_WHITE_LABEL))
                    {{-- Per our license, please do not remove or modify this link. --}}
                    <a class="navbar-brand" href="{{ URL::to(NINJA_WEB_URL) }}" target="_blank"><img
                                src="{{ asset('images/invoiceninja-logo.png') }}" style="height:27px"></a>
                @endif
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                @if (isset($account) && $account->enable_client_portal)
                <ul class="nav navbar-nav navbar-right">
                    @if (isset($account) && $account->enable_client_portal_dashboard)
                        <li {!! Request::is('*client/dashboard*') ? 'class="active"' : '' !!}>
                            {!! link_to('/client/dashboard', trans('texts.dashboard') ) !!}
                        </li>
                    @endif
                    @if (isset($hasQuotes) && $hasQuotes)
                        <li {!! Request::is('*client/quotes') ? 'class="active"' : '' !!}>
                            {!! link_to('/client/quotes', trans('texts.quotes') ) !!}
                        </li>
                    @endif
                    <li {!! Request::is('*client/invoices') ? 'class="active"' : '' !!}>
                        {!! link_to('/client/invoices', trans('texts.invoices') ) !!}
                    </li>
                    @if (isset($account)
                        && $account->hasFeature(FEATURE_DOCUMENTS)
                        && (isset($hasDocuments) && $hasDocuments))
                        <li {!! Request::is('*client/documents') ? 'class="active"' : '' !!}>
                            {!! link_to('/client/documents', trans('texts.documents') ) !!}
                        </li>
                    @endif
                    @if (isset($hasPaymentMethods) && $hasPaymentMethods)
                        <li {!! Request::is('*client/payment_methods') ? 'class="active"' : '' !!}>
                            {!! link_to('/client/payment_methods', trans('texts.payment_methods') ) !!}
                        </li>
                    @endif
                    <li {!! Request::is('*client/payments') ? 'class="active"' : '' !!}>
                        {!! link_to('/client/payments', trans('texts.payments') ) !!}
                    </li>
                    @if (isset($hasCredits) && $hasCredits)
                        <li {!! Request::is('*client/credits') ? 'class="active"' : '' !!}>
                            {!! link_to('/client/credits', trans('texts.credits') ) !!}
                        </li>
                    @endif
                </ul>
                @endif
            </div><!--/.nav-collapse -->
        </div>
    </nav>

    <div class="container">

      @include('partials.warn_session', ['redirectTo' => '/'])

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
</div>

<div id="mainContent">
    @yield('content')
</div>

<footer id="footer" role="contentinfo">
    <div class="top">
        <div class="wrap">
            @if (!isset($account) || !$account->hasFeature(FEATURE_WHITE_LABEL))
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
            @if (!isset($account) || !$account->hasFeature(FEATURE_WHITE_LABEL))
                <div class="copy">Copyright &copy;{{ date('Y') }} <a href="{{ NINJA_WEB_URL }}" target="_blank">Invoice Ninja</a>. All rights reserved.</div>
            @endif
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
