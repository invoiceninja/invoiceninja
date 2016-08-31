@extends('master')


@section('head')

  <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

  <style type="text/css">

  .menu-toggle {
      color: #999 !important;
      text-decoration: none;
  }

  .menu-toggle:hover {
      color: #fff !important;
      text-decoration: none;
  }

  /*!
   * Start Bootstrap - Simple Sidebar (http://startbootstrap.com/)
   * Copyright 2013-2016 Start Bootstrap
   * Licensed under MIT (https://github.com/BlackrockDigital/startbootstrap/blob/gh-pages/LICENSE)
   */

   body {
       overflow-x: hidden;
   }

  /* Toggle Styles */

  #wrapper {
      padding-left: 0;
      padding-right: 0;
      -webkit-transition: all 0.5s ease;
      -moz-transition: all 0.5s ease;
      -o-transition: all 0.5s ease;
      transition: all 0.5s ease;
  }

  #wrapper.toggled-left {
      padding-left: 250px;
  }

  #wrapper.toggled-right {
      padding-right: 250px;
  }

  #left-sidebar-wrapper {
      z-index: 1000;
      position: fixed;
      left: 250px;
      width: 0;
      height: 100%;
      margin-left: -250px;
      overflow-y: auto;
      background: #222;
      -webkit-transition: all 0.5s ease;
      -moz-transition: all 0.5s ease;
      -o-transition: all 0.5s ease;
      transition: all 0.5s ease;
  }

  #right-sidebar-wrapper {
      z-index: 1000;
      position: fixed;
      top: 0px;
      right: 250px;
      width: 0px;
      height: 100%;
      margin-right: -250px;
      overflow-y: auto;
      background: #222;
      -webkit-transition: all 0.5s ease;
      -moz-transition: all 0.5s ease;
      -o-transition: all 0.5s ease;
      transition: all 0.5s ease;
  }

  #wrapper.toggled-left #left-sidebar-wrapper {
      width: 250px;
  }

  #wrapper.toggled-right #right-sidebar-wrapper {
      width: 250px;
  }

  #page-content-wrapper {
      width: 100%;
      position: absolute;
      padding: 15px;
  }

  #wrapper.toggled-left #page-content-wrapper {
      position: absolute;
      margin-right: -250px;
  }

  #wrapper.toggled-right #page-content-wrapper {
      position: absolute;
      padding-right: -250px;
  }

  /* Sidebar Styles */

  .sidebar-nav {
      position: absolute;
      top: 0;
      width: 250px;
      margin: 0;
      padding: 0;
      list-style: none;
      height: 100%;
  }

  .sidebar-nav li {
      text-indent: 20px;
      line-height: 40px;
  }

  .sidebar-nav li > a {
      display: block;
      text-decoration: none;
      color: #999999;
      cursor: pointer;
  }

  .sidebar-nav li:hover > a,
  .sidebar-nav li > a.active {
      text-decoration: none;
      color: #fff;
  }

  .sidebar-nav li:hover,
  .sidebar-nav li.active {
      background: rgba(255,255,255,0.2);
  }

  .sidebar-nav li > a:hover {
      text-decoration: none;
  }

  .sidebar-nav li > a.btn {
      display: none;
  }

  .sidebar-nav li:hover > a {
      display: block;
  }

  .sidebar-nav > .sidebar-brand {
      height: 65px;
      font-size: 18px;
      line-height: 60px;
  }

  .sidebar-nav > .sidebar-brand a {
      color: #999999;
  }

  .sidebar-nav > .sidebar-brand a:hover {
      color: #fff;
      background: none;
  }

    @media(min-width:768px) {
      #wrapper {
          padding-left: 250px;
          padding-right: 250px;
      }

      #wrapper.toggled-left {
          padding-left: 0;
      }

      #wrapper.toggled-right {
          padding-right: 0;
      }

      #left-sidebar-wrapper {
          width: 250px;
      }

      #right-sidebar-wrapper {
          width: 250px;
      }

      #wrapper.toggled-left #left-sidebar-wrapper {
          width: 0;
      }

      #wrapper.toggled-right #right-sidebar-wrapper {
          width: 0;
      }

      #page-content-wrapper {
          padding: 20px;
          position: relative;
      }

      #wrapper.toggled-left #page-content-wrapper {
          position: relative;
          margin-right: 0;
      }

      #wrapper.toggled-right #page-content-wrapper {
          position: relative;
          margin-right: 0;
      }
    }

    body {
      background-color: #EEEEEE;
      padding-top: 56px;
    }

    @if (Auth::check() && Auth::user()->dark_mode)
        body {
            background: #000 !important;
            color: white !important;
        }

        .panel-body {
            background: #272822 !important;
            /*background: #e6e6e6 !important;*/
        }

        .panel-default {
            border-color: #444;
        }
    @endif

  </style>

<script type="text/javascript">

  @if (!Auth::check() || !Auth::user()->registered)
  function validateSignUp(showError)
  {
    var isFormValid = true;
    $(['first_name','last_name','email','password']).each(function(i, field) {
      var $input = $('form.signUpForm #new_'+field),
      val = $.trim($input.val());
      var isValid = val && val.length >= (field == 'password' ? 6 : 1);
      if (isValid && field == 'email') {
        isValid = isValidEmailAddress(val);
      }
      if (isValid) {
        $input.closest('div.form-group').removeClass('has-error').addClass('has-success');
      } else {
        isFormValid = false;
        $input.closest('div.form-group').removeClass('has-success');
        if (showError) {
          $input.closest('div.form-group').addClass('has-error');
        }
      }
    });

    if (!$('#terms_checkbox').is(':checked')) {
      isFormValid = false;
    }

    $('#saveSignUpButton').prop('disabled', !isFormValid);

    return isFormValid;
  }

  function validateServerSignUp()
  {
    if (!validateSignUp(true)) {
      return;
    }

    $('#signUpDiv, #signUpFooter').hide();
    $('#working').show();

    $.ajax({
      type: 'POST',
      url: '{{ URL::to('signup/validate') }}',
      data: 'email=' + $('form.signUpForm #new_email').val(),
      success: function(result) {
        if (result == 'available') {
          submitSignUp();
        } else {
          $('#errorTaken').show();
          $('form.signUpForm #new_email').closest('div.form-group').removeClass('has-success').addClass('has-error');
          $('#signUpDiv, #signUpFooter').show();
          $('#working').hide();
        }
      }
    });
  }

  function submitSignUp() {
    $.ajax({
      type: 'POST',
      url: '{{ URL::to('signup/submit') }}',
      data: 'new_email=' + encodeURIComponent($('form.signUpForm #new_email').val()) +
      '&new_password=' + encodeURIComponent($('form.signUpForm #new_password').val()) +
      '&new_first_name=' + encodeURIComponent($('form.signUpForm #new_first_name').val()) +
      '&new_last_name=' + encodeURIComponent($('form.signUpForm #new_last_name').val()) +
      '&go_pro=' + $('#go_pro').val(),
      success: function(result) {
        if (result) {
          handleSignedUp();
          NINJA.isRegistered = true;
          $('#signUpButton').hide();
          $('#myAccountButton').html(result);
        }
        $('#signUpSuccessDiv, #signUpFooter, #closeSignUpButton').show();
        $('#working, #saveSignUpButton').hide();
      }
    });
  }
  @endif

  function handleSignedUp() {
      localStorage.setItem('guest_key', '');
      fbq('track', 'CompleteRegistration');
      window._fbq.push(['track', '{{ env('FACEBOOK_PIXEL_SIGN_UP') }}', {'value':'0.00','currency':'USD'}]);
      trackEvent('/account', '/signed_up');
  }

  function checkForEnter(event)
  {
    if (event.keyCode === 13){
      event.preventDefault();
      validateServerSignUp();
      return false;
    }
  }

  function logout(force)
  {
    if (force) {
      NINJA.formIsChanged = false;
    }

    if (force || NINJA.isRegistered) {
      window.location = '{{ URL::to('logout') }}';
    } else {
      $('#logoutModal').modal('show');
    }
  }

  function showSignUp() {
    $('#signUpModal').modal('show');
  }

  function hideSignUp() {
    $('#signUpModal').modal('hide');
  }

  function buyProduct(affiliateKey, productId) {
    window.open('{{ Utils::isNinjaDev() ? '' : NINJA_APP_URL }}/license?affiliate_key=' + affiliateKey + '&product_id=' + productId + '&return_url=' + window.location);
  }

  function hideMessage() {
    $('.alert-info').fadeOut();
    $.get('/hide_message', function(response) {
      console.log('Reponse: %s', response);
    });
  }

  function wordWrapText(value, width)
  {
    @if (Auth::user()->account->auto_wrap)
    var doc = new jsPDF('p', 'pt');
    doc.setFont('Helvetica','');
    doc.setFontSize(10);

    var lines = value.split("\n");
    for (var i = 0; i < lines.length; i++) {
      var numLines = doc.splitTextToSize(lines[i], width).length;
      if (numLines <= 1) continue;
      var j = 0; space = lines[i].length;
      while (j++ < lines[i].length) {
        if (lines[i].charAt(j) === ' ') space = j;
      }
      if (space == lines[i].length) space = width/6;
      lines[i + 1] = lines[i].substring(space + 1) + ' ' + (lines[i + 1] || '');
      lines[i] = lines[i].substring(0, space);
    }

    var newValue = (lines.join("\n")).trim();

    if (value == newValue) {
      return newValue;
    } else {
      return wordWrapText(newValue, width);
    }
    @else
    return value;
    @endif
  }

  function setSignupEnabled(enabled) {
    $('.signup-form input[type=text]').prop('disabled', !enabled);
    if (enabled) {
        $('.signup-form a.btn').removeClass('disabled');
    } else {
        $('.signup-form a.btn').addClass('disabled');
    }
  }

  function setSocialLoginProvider(provider) {
    localStorage.setItem('auth_provider', provider);
  }

  window.loadedSearchData = false;
  function onSearchFocus() {
    $('#search').typeahead('val', '');
    $('#search-form').show();

    if (!window.loadedSearchData) {
        window.loadedSearchData = true;
        trackEvent('/activity', '/search');
        var request = $.get('{{ URL::route('get_search_data') }}', function(data) {
          $('#search').typeahead({
            hint: true,
            highlight: true,
          }
          @if (Auth::check() && Auth::user()->account->custom_client_label1)
          ,{
            name: 'data',
            limit: 3,
            display: 'value',
            source: searchData(data['{{ Auth::user()->account->custom_client_label1 }}'], 'tokens'),
            templates: {
              header: '&nbsp;<span style="font-weight:600;font-size:16px">{{ Auth::user()->account->custom_client_label1 }}</span>'
            }
          }
          @endif
          @if (Auth::check() && Auth::user()->account->custom_client_label2)
          ,{
            name: 'data',
            limit: 3,
            display: 'value',
            source: searchData(data['{{ Auth::user()->account->custom_client_label2 }}'], 'tokens'),
            templates: {
              header: '&nbsp;<span style="font-weight:600;font-size:16px">{{ Auth::user()->account->custom_client_label2 }}</span>'
            }
          }
          @endif
          @foreach (['clients', 'contacts', 'invoices', 'quotes', 'navigation'] as $type)
          ,{
            name: 'data',
            limit: 3,
            display: 'value',
            source: searchData(data['{{ $type }}'], 'tokens', true),
            templates: {
              header: '&nbsp;<span style="font-weight:600;font-size:16px">{{ trans("texts.{$type}") }}</span>'
            }
          }
          @endforeach
          ).on('typeahead:selected', function(element, datum, name) {
            window.location = datum.url;
          }).focus();
        });

        request.error(function(httpObj, textStatus) {
            // if the session has expried show login page
            if (httpObj.status == 401) {
                location.reload();
            }
        });
    }
  }

  $(function() {
    window.setTimeout(function() {
        $(".alert-hide").fadeOut();
    }, 3000);

    /* Set the defaults for Bootstrap datepicker */
    $.extend(true, $.fn.datepicker.defaults, {
        //language: '{{ $appLanguage }}', // causes problems with some languages (ie, fr_CA) if the date includes strings (ie, July 31, 2016)
        weekStart: {{ Session::get('start_of_week') }}
    });

    if (isStorageSupported()) {
      @if (Auth::check() && !Auth::user()->registered)
      localStorage.setItem('guest_key', '{{ Auth::user()->password }}');
      @endif
    }

    @if (!Auth::check() || !Auth::user()->registered)
    validateSignUp();

    $('#signUpModal').on('shown.bs.modal', function () {
      trackEvent('/account', '/view_sign_up');
      $(['first_name','last_name','email','password']).each(function(i, field) {
        var $input = $('form.signUpForm #new_'+field);
        if (!$input.val()) {
          $input.focus();
          return false;
        }
      });
    })
    @endif

    @if (Auth::check() && !Utils::isNinja() && !Auth::user()->registered)
      $('#closeSignUpButton').hide();
      showSignUp();
    @elseif(Session::get('sign_up') || Input::get('sign_up'))
      showSignUp();
    @endif

    $('ul.navbar-settings, ul.navbar-search').hover(function () {
        if ($('.user-accounts').css('display') == 'block') {
            $('.user-accounts').dropdown('toggle');
        }
    });

    @yield('onReady')

    @if (Input::has('focus'))
        $('#{{ Input::get('focus') }}').focus();
    @endif

    // Ensure terms is checked for sign up form
    @if (Auth::check() && !Auth::user()->registered)
        setSignupEnabled(false);
        $("#terms_checkbox").change(function() {
            setSignupEnabled(this.checked);
        });
    @endif

    // Focus the search input if the user clicks forward slash
    $('#search').focusin(onSearchFocus);

    $('body').keypress(function(event) {
        if (event.which == 47 && !$('*:focus').length) {
            event.preventDefault();
            $('#search').focus();
        }
    });

    // manage sidebar state
    $("#left-menu-toggle").click(function(e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled-left");

        var toggled = $("#wrapper").hasClass("toggled-left") ? '1' : '0';
        $.get('{{ url('save_sidebar_state') }}?show_left=' + toggled);
    });

    $("#right-menu-toggle").click(function(e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled-right");

        var toggled = $("#wrapper").hasClass("toggled-right") ? '1' : '0';
        $.get('{{ url('save_sidebar_state') }}?show_right=' + toggled);
    });

  });

</script>

@stop

@section('body')

<nav class="navbar navbar-default navbar-fixed-top" role="navigation" style="height:60px;">

    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-collapse-1">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <div class="navbar-brand">
          <a href="#" id="left-menu-toggle" class="menu-toggle hide-phone" title="{{ trans('texts.toggle_navigation') }}">
            <i class="fa fa-bars" style="width:30px;padding-right:10px"></i>
          </a>
          <a href="{{ URL::to(NINJA_WEB_URL) }}" target="_blank">
            {{-- Per our license, please do not remove or modify this link. --}}
            <img src="{{ asset('images/invoiceninja-logo.png') }}" width="193" height="25"/>
          </a>
      </div>
    </div>

    <div class="collapse navbar-collapse" id="navbar-collapse-1">
      <div class="navbar-form navbar-right" style="padding-right:30px">

        @if (Auth::check())
          @if (!Auth::user()->registered)
            {!! Button::success(trans('texts.sign_up'))->withAttributes(array('id' => 'signUpButton', 'data-toggle'=>'modal', 'data-target'=>'#signUpModal', 'style' => 'max-width:100px;;overflow:hidden'))->small() !!} &nbsp;
          @elseif (Utils::isNinjaProd() && (!Auth::user()->isPro() || Auth::user()->isTrial()))
            {!! Button::success(trans('texts.plan_upgrade'))->asLinkTo(url('/settings/account_management?upgrade=true'))->withAttributes(array('style' => 'max-width:100px;overflow:hidden'))->small() !!} &nbsp;
          @endif
        @endif

        <div class="btn-group user-dropdown">
          <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
            <div id="myAccountButton" class="ellipsis" style="max-width:{{ Utils::hasFeature(FEATURE_USERS) ? '130' : '100' }}px;">
                @if (session(SESSION_USER_ACCOUNTS) && count(session(SESSION_USER_ACCOUNTS)))
                    {{ Auth::user()->account->getDisplayName() }}
                @else
                    {{ Auth::user()->getDisplayName() }}
                @endif
              <span class="caret"></span>
            </div>
          </button>
          <ul class="dropdown-menu user-accounts">
            @if (session(SESSION_USER_ACCOUNTS))
                @foreach (session(SESSION_USER_ACCOUNTS) as $item)
                    @if ($item->user_id == Auth::user()->id)
                        @include('user_account', [
                            'user_account_id' => $item->id,
                            'user_id' => $item->user_id,
                            'account_name' => $item->account_name,
                            'user_name' => $item->user_name,
                            'logo_url' => isset($item->logo_url) ? $item->logo_url : "",
                            'selected' => true,
                        ])
                    @endif
                @endforeach
                @foreach (session(SESSION_USER_ACCOUNTS) as $item)
                    @if ($item->user_id != Auth::user()->id)
                        @include('user_account', [
                            'user_account_id' => $item->id,
                            'user_id' => $item->user_id,
                            'account_name' => $item->account_name,
                            'user_name' => $item->user_name,
                            'logo_url' => isset($item->logo_url) ? $item->logo_url : "",
                            'selected' => false,
                        ])
                    @endif
                @endforeach
            @else
                @include('user_account', [
                    'account_name' => Auth::user()->account->name ?: trans('texts.untitled'),
                    'user_name' => Auth::user()->getDisplayName(),
                    'logo_url' => Auth::user()->account->getLogoURL(),
                    'selected' => true,
                ])
            @endif
            <li class="divider"></li>
            @if (Utils::isAdmin())
              @if (count(session(SESSION_USER_ACCOUNTS)) > 1)
                  <li>{!! link_to('/manage_companies', trans('texts.manage_companies')) !!}</li>
              @elseif (!session(SESSION_USER_ACCOUNTS) || count(session(SESSION_USER_ACCOUNTS)) < 5)
                  <li>{!! link_to('/login?new_company=true', trans('texts.add_company')) !!}</li>
              @endif
            @endif
            <li>{!! link_to('#', trans('texts.logout'), array('onclick'=>'logout()')) !!}</li>
          </ul>
        </div>

        <a href="#" id="right-menu-toggle" class="menu-toggle hide-phone" title="{{ trans('texts.toggle_history') }}">
          <i class="fa fa-bars" style="width:30px;padding-left:14px"></i>
        </a>

      </div>

      <form id="search-form" class="navbar-form navbar-right" role="search">
        <div class="form-group">
          <input type="text" id="search" style="width: 240px;padding-top:0px;padding-bottom:0px"
            class="form-control" placeholder="{{ trans('texts.search') . ': ' . trans('texts.search_hotkey')}}">
        </div>
      </form>

      @if (false && Utils::isAdmin())
      <ul class="nav navbar-nav navbar-right">
        <li class="dropdown">
           @section('self-updater')
            <a href="{{ URL::to('self-update') }}" class="dropdown-toggle">
              <span class="glyphicon glyphicon-cloud-download" title="{{ trans('texts.update_invoiceninja_title') }}"></span>
            </a>
          @show
        </li>
      </ul>
      @endif

      <ul class="nav navbar-nav hide-non-phone" style="font-weight: bold">
        @foreach ([
            'dashboard' => false,
            'clients' => false,
            'credits' => false,
            'tasks' => false,
            'expenses' => false,
            'vendors' => false,
            'quotes' => false,
            'invoices' => false,
            'recurring_invoices' => 'recurring',
            'payments' => false,
            'settings' => false,
        ] as $key => $value)
            {!! Form::nav_link($key, $value ?: $key) !!}
        @endforeach
      </ul>

    </div><!-- /.navbar-collapse -->

</nav>

<div id="wrapper" class='{!! session(SESSION_LEFT_SIDEBAR) ? 'toggled-left' : '' !!} {!! session(SESSION_RIGHT_SIDEBAR, true) ? 'toggled-right' : '' !!}'>

    <!-- Sidebar -->
    <div id="left-sidebar-wrapper">
        <ul class="sidebar-nav" style="padding-top:20px">
            @foreach([
                'dashboard' => 'tachometer',
                'clients' => 'users',
                'invoices' => 'file-pdf-o',
                'payments' => 'credit-card',
                'recurring_invoices' => 'files-o',
                'credits' => 'credit-card',
                'quotes' => 'file-text-o',
                'tasks' => 'clock-o',
                'expenses' => 'file-image-o',
                'vendors' => 'building',
                'settings' => 'cog',
            ] as $option => $icon)
            <li style="border-bottom:solid 1px" class="{{ Request::is("{$option}*") ? 'active' : '' }}">
                @if ($option != 'dashboard' && $option != 'settings')
                    @if (Auth::user()->can('create', substr($option, 0, -1)))
                        <a type="button" class="btn btn-primary btn-sm pull-right" style="margin-top:10px;margin-right:10px;text-indent:0px"
                            href="{{ url("/{$option}/create") }}">
                            <i class="fa fa-plus-circle" style="color:white;width:20px" title="{{ trans('texts.create_new') }}"></i>
                        </a>
                    @endif
                @endif
                <a href="{{ url($option == 'recurring' ? 'recurring_invoice' : $option) }}"
                    style="font-size:16px; padding-top:6px; padding-bottom:6px"
                    class="{{ Request::is("{$option}*") ? 'active' : '' }}">
                    <i class="fa fa-{{ $icon }}" style="width:46px; color:white; padding-right:10px"></i>
                    {{ ($option == 'recurring_invoices') ? trans('texts.recurring') : trans("texts.{$option}") }}
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    <!-- /#left-sidebar-wrapper -->

    <div id="right-sidebar-wrapper">
        SIDEBAR
    </div>

    <!-- Page Content -->
    <div id="page-content-wrapper">
        <div class="container-fluid">

          @include('partials.warn_session', ['redirectTo' => '/dashboard'])

          @if (Session::has('warning'))
          <div class="alert alert-warning">{!! Session::get('warning') !!}</div>
          @endif

          @if (Session::has('message'))
            <div class="alert alert-info alert-hide">
              {{ Session::get('message') }}
            </div>
          @elseif (Session::has('news_feed_message'))
            <div class="alert alert-info">
              {!! Session::get('news_feed_message') !!}
              <a href="#" onclick="hideMessage()" class="pull-right">{{ trans('texts.hide') }}</a>
            </div>
          @endif

          @if (Session::has('error'))
              <div class="alert alert-danger">{!! Session::get('error') !!}</div>
          @endif

          @if (!isset($showBreadcrumbs) || $showBreadcrumbs)
            {!! Form::breadcrumbs(isset($entityStatus) ? $entityStatus : '') !!}
          @endif

          @yield('content')

          <div class="row">
            <div class="col-md-12">

              @if (Utils::isNinjaProd())
                @if (Auth::check() && Auth::user()->isTrial())
                  {!! trans(Auth::user()->account->getCountTrialDaysLeft() == 0 ? 'texts.trial_footer_last_day' : 'texts.trial_footer', [
                          'count' => Auth::user()->account->getCountTrialDaysLeft(),
                          'link' => link_to('/settings/account_management?upgrade=true', trans('texts.click_here'))
                      ]) !!}
                @endif
              @else
                {{ trans('texts.powered_by') }}
                {{-- Per our license, please do not remove or modify this section. --}}
                {!! link_to('https://www.invoiceninja.com/?utm_source=powered_by', 'InvoiceNinja.com', ['target' => '_blank', 'title' => 'invoiceninja.com']) !!} -
                {!! link_to(RELEASES_URL, 'v' . NINJA_VERSION, ['target' => '_blank', 'title' => trans('texts.trello_roadmap')]) !!} |
                @if (Auth::user()->account->hasFeature(FEATURE_WHITE_LABEL))
                  {{ trans('texts.white_labeled') }}
                @else
                  <a href="#" onclick="loadImages('#whiteLabelModal');$('#whiteLabelModal').modal('show');">{{ trans('texts.white_label_link') }}</a>

                  <div class="modal fade" id="whiteLabelModal" tabindex="-1" role="dialog" aria-labelledby="whiteLabelModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <div class="modal-header">
                          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                          <h4 class="modal-title" id="myModalLabel">{{ trans('texts.white_label_header') }}</h4>
                        </div>

                        <div class="panel-body">
                          <p>{{ trans('texts.white_label_text', ['price' => WHITE_LABEL_PRICE])}}</p>
                          <div class="row">
                              <div class="col-md-6">
                                  <h4>{{ trans('texts.before') }}</h4>
                                  <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('images/pro_plan/white_label_before.png') }}" width="100%" alt="before">
                              </div>
                              <div class="col-md-6">
                                  <h4>{{ trans('texts.after') }}</h4>
                                  <img src="{{ BLANK_IMAGE }}" data-src="{{ asset('images/pro_plan/white_label_after.png') }}" width="100%" alt="after">
                              </div>
                          </div>
                        </div>

                        <div class="modal-footer" id="signUpFooter" style="margin-top: 0px">
                          <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }} </button>
                          {{-- DropdownButton::success_lg(trans('texts.buy'), [
                              ['url' => URL::to(""), 'label' => trans('texts.pay_with_paypal')],
                              ['url' => URL::to(""), 'label' => trans('texts.pay_with_card')]
                          ])->addClass('btn-lg') --}}
                          <button type="button" class="btn btn-primary" onclick="buyProduct('{{ WHITE_LABEL_AFFILIATE_KEY }}', '{{ PRODUCT_WHITE_LABEL }}')">{{ trans('texts.buy') }} </button>
                        </div>
                      </div>
                    </div>
                  </div>
                @endif
              </div>
              @endif
            </div>
        </div>

    </div>
    <!-- /#page-content-wrapper -->
</div>


@if (!Auth::check() || !Auth::user()->registered)
<div class="modal fade" id="signUpModal" tabindex="-1" role="dialog" aria-labelledby="signUpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="myModalLabel">{{ trans('texts.sign_up') }}</h4>
      </div>

      <div style="background-color: #fff; padding-right:20px" id="signUpDiv" onkeyup="validateSignUp()" onclick="validateSignUp()" onkeydown="checkForEnter(event)">
        <br/>

        {!! Former::open('signup/submit')->addClass('signUpForm')->autocomplete('on') !!}

        @if (Auth::check())
        {!! Former::populateField('new_first_name', Auth::user()->first_name) !!}
        {!! Former::populateField('new_last_name', Auth::user()->last_name) !!}
        {!! Former::populateField('new_email', Auth::user()->email) !!}
        @endif

        <div style="display:none">
          {!! Former::text('path')->value(Request::path()) !!}
          {!! Former::text('go_pro') !!}
        </div>


        <div class="row signup-form">
            <div class="col-md-11 col-md-offset-1">
                {!! Former::checkbox('terms_checkbox')->label(' ')->text(trans('texts.agree_to_terms', ['terms' => '<a href="'.URL::to('terms').'" target="_blank">'.trans('texts.terms_of_service').'</a>']))->raw() !!}
                <br/>
            </div>
            @if (Utils::isOAuthEnabled())
                <div class="col-md-4 col-md-offset-1">
                    <h4>{{ trans('texts.sign_up_using') }}</h4><br/>
                    @foreach (App\Services\AuthService::$providers as $provider)
                    <a href="{{ URL::to('auth/' . $provider) }}" class="btn btn-primary btn-block"
                        onclick="setSocialLoginProvider('{{ strtolower($provider) }}')" id="{{ strtolower($provider) }}LoginButton">
                        <i class="fa fa-{{ strtolower($provider) }}"></i> &nbsp;
                        {{ $provider }}
                    </a>
                    @endforeach
                </div>
                <div class="col-md-1">
                    <div style="border-right:thin solid #CCCCCC;height:110px;width:8px;margin-bottom:10px;"></div>
                    {{ trans('texts.or') }}
                    <div style="border-right:thin solid #CCCCCC;height:110px;width:8px;margin-top:10px;"></div>
                </div>
                <div class="col-md-6">
            @else
                <div class="col-md-12">
            @endif
                {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 1) }}
                {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 1) }}

                {!! Former::text('new_first_name')
                        ->placeholder(trans('texts.first_name'))
                        ->autocomplete('given-name')
                        ->label(' ') !!}
                {!! Former::text('new_last_name')
                        ->placeholder(trans('texts.last_name'))
                        ->autocomplete('family-name')
                        ->label(' ') !!}
                {!! Former::text('new_email')
                        ->placeholder(trans('texts.email'))
                        ->autocomplete('email')
                        ->label(' ') !!}
                {!! Former::password('new_password')
                        ->placeholder(trans('texts.password'))
                        ->label(' ') !!}

                {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
                {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}
            </div>

            <div class="col-md-11 col-md-offset-1">
                <div style="padding-top:20px;padding-bottom:10px;">{{ trans('texts.trial_message') }}</div>
            </div>
        </div>

        {!! Former::close() !!}



        <center><div id="errorTaken" style="display:none">&nbsp;<br/>{{ trans('texts.email_taken') }}</div></center>
        <br/>

      </div>

      <div style="padding-left:40px;padding-right:40px;display:none;min-height:130px" id="working">
        <h3>{{ trans('texts.working') }}...</h3>
        <div class="progress progress-striped active">
          <div class="progress-bar"  role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
        </div>
      </div>

      <div style="background-color: #fff; padding-right:20px;padding-left:20px; display:none" id="signUpSuccessDiv">
        <br/>
        <h3>{{ trans('texts.success') }}</h3>
        @if (Utils::isNinja())
          {{ trans('texts.success_message') }}
        @endif
        <br/>&nbsp;
      </div>

      <div class="modal-footer" id="signUpFooter" style="margin-top: 0px">
        <button type="button" class="btn btn-default" id="closeSignUpButton" data-dismiss="modal">{{ trans('texts.close') }} <i class="glyphicon glyphicon-remove-circle"></i></button>
        <button type="button" class="btn btn-primary" id="saveSignUpButton" onclick="validateServerSignUp()" disabled>{{ trans('texts.save') }} <i class="glyphicon glyphicon-floppy-disk"></i></button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="myModalLabel">{{ trans('texts.logout') }}</h4>
      </div>

      <div class="container">
        <h3>{{ trans('texts.are_you_sure') }}</h3>
        <p>{{ trans('texts.erase_data') }}</p>
      </div>

      <div class="modal-footer" id="signUpFooter">
        <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
        <button type="button" class="btn btn-primary" onclick="logout(true)">{{ trans('texts.logout') }}</button>
      </div>
    </div>
  </div>
</div>
@endif

</div>

<p>&nbsp;</p>


@stop
