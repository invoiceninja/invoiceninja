@extends('master')


@section('head')

  <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
  <style type="text/css">
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

  function hideMessage() {
    $('.alert-info').fadeOut();
    $.get('/hide_message', function(response) {
      console.log('Reponse: %s', response);
    });
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
  function onSearchBlur() {
      $('#search').typeahead('val', '');
  }

  function onSearchFocus() {
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
    $('#search').blur(onSearchBlur);

    // manage sidebar state
    function setupSidebar(side) {
        $("#" + side + "-menu-toggle").click(function(e) {
            e.preventDefault();
            $("#wrapper").toggleClass("toggled-" + side);

            var toggled = $("#wrapper").hasClass("toggled-" + side) ? '1' : '0';
            $.post('{{ url('save_sidebar_state') }}?show_' + side + '=' + toggled);

            if (isStorageSupported()) {
                localStorage.setItem('show_' + side + '_sidebar', toggled);
            }
        });

        if (isStorageSupported()) {
            var storage = localStorage.getItem('show_' + side + '_sidebar') || '0';
            var toggled = $("#wrapper").hasClass("toggled-" + side) ? '1' : '0';

            if (storage != toggled) {
                setTimeout(function() {
                    $("#wrapper").toggleClass("toggled-" + side);
                    $.post('{{ url('save_sidebar_state') }}?show_' + side + '=' + storage);
                }, 200);
            }
        }
    }

    @if ( ! Utils::isTravis())
        setupSidebar('left');
        setupSidebar('right');
    @endif

    // auto select focused nav-tab
    if (window.location.hash) {
        setTimeout(function() {
            $('.nav-tabs a[href="' + window.location.hash + '"]').tab('show');
        }, 1);
    }

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr("href") // activated tab
        if (history.pushState) {
            history.pushState(null, null, target);
        }
    });

  });

</script>

@stop

@section('body')

@if ( ! Request::is('settings/account_management'))
  @include('partials.upgrade_modal')
@endif

<nav class="navbar navbar-default navbar-fixed-top" role="navigation" style="height:60px;">

    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-collapse-1">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a href="#" id="left-menu-toggle" class="menu-toggle" title="{{ trans('texts.toggle_navigation') }}">
          <div class="navbar-brand">
                <i class="fa fa-bars hide-phone" style="width:32px;padding-top:2px;float:left"></i>
                {{-- Per our license, please do not remove or modify this link. --}}
                <img src="{{ asset('images/invoiceninja-logo.png') }}" width="193" height="25" style="float:left"/>
          </div>
      </a>
    </div>

    <a id="right-menu-toggle" class="menu-toggle hide-phone pull-right" title="{{ trans('texts.toggle_history') }}" style="cursor:pointer">
      <div class="fa fa-bars"></div>
    </a>

    <div class="collapse navbar-collapse" id="navbar-collapse-1">
      <div class="navbar-form navbar-right">

        @if (Auth::check())
          @if (!Auth::user()->registered)
            {!! Button::success(trans('texts.sign_up'))->withAttributes(array('id' => 'signUpButton', 'data-toggle'=>'modal', 'data-target'=>'#signUpModal', 'style' => 'max-width:100px;;overflow:hidden'))->small() !!} &nbsp;
          @elseif (Utils::isNinjaProd() && (!Auth::user()->isPro() || Auth::user()->isTrial()))
            @if (Auth::user()->account->company->hasActivePromo())
                {!! Button::warning(trans('texts.plan_upgrade'))->withAttributes(array('onclick' => 'showUpgradeModal()', 'style' => 'max-width:100px;overflow:hidden'))->small() !!} &nbsp;
            @else
                {!! Button::success(trans('texts.plan_upgrade'))->withAttributes(array('onclick' => 'showUpgradeModal()', 'style' => 'max-width:100px;overflow:hidden'))->small() !!} &nbsp;
            @endif
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
            'products' => false,
            'invoices' => false,
            'payments' => false,
            'recurring_invoices' => 'recurring',
            'credits' => false,
            'quotes' => false,
            'tasks' => false,
            'expenses' => false,
            'vendors' => false,
            'settings' => false,
        ] as $key => $value)
            {!! Form::nav_link($key, $value ?: $key) !!}
        @endforeach
      </ul>
    </div><!-- /.navbar-collapse -->

</nav>

<div id="wrapper" class='{!! session(SESSION_LEFT_SIDEBAR) ? 'toggled-left' : '' !!} {!! session(SESSION_RIGHT_SIDEBAR, true) ? 'toggled-right' : '' !!}'>

    <!-- Sidebar -->
    <div id="left-sidebar-wrapper" class="hide-phone">
        <ul class="sidebar-nav">
            @foreach([
                'dashboard',
                'clients',
                'products',
                'invoices',
                'payments',
                'recurring_invoices',
                'credits',
                'quotes',
                'tasks',
                'expenses',
                'vendors',
            ] as $option)
            @if (in_array($option, ['dashboard', 'settings'])
                || Auth::user()->can('view', substr($option, 0, -1))
                || Auth::user()->can('create', substr($option, 0, -1)))
                @include('partials.navigation_option')
            @endif
        @endforeach
        @if ( ! Utils::isNinjaProd())
            @foreach (Module::all() as $module)
                @include('partials.navigation_option', [
                    'option' => $module->getAlias(),
                    'icon' => $module->get('icon', 'th-large'),
                ])
            @endforeach
        @endif
        @include('partials.navigation_option', ['option' => 'settings'])
            <li style="width:100%">
                <div class="nav-footer">
                    <a href="{{ url(NINJA_CONTACT_URL) }}" target="_blank" title="{{ trans('texts.contact_us') }}">
                        <i class="fa fa-envelope"></i>
                    </a>
                    <a href="{{ url(NINJA_FORUM_URL) }}" target="_blank" title="{{ trans('texts.support_forum') }}">
                        <i class="fa fa-list-ul"></i>
                    </a>
                    <a href="javascript:showKeyboardShortcuts()" target="_blank" title="{{ trans('texts.keyboard_shortcuts') }}">
                        <i class="fa fa-question-circle"></i>
                    </a>
                    <a href="{{ url(SOCIAL_LINK_FACEBOOK) }}" target="_blank" title="Facebook">
                        <i class="fa fa-facebook-square"></i>
                    </a>
                    <a href="{{ url(SOCIAL_LINK_TWITTER) }}" target="_blank" title="Twitter">
                        <i class="fa fa-twitter-square"></i>
                    </a>
                    <a href="{{ url(SOCIAL_LINK_GITHUB) }}" target="_blank" title="GitHub">
                        <i class="fa fa-github-square"></i>
                    </a>
                </div>
            </li>
        </ul>
    </div>
    <!-- /#left-sidebar-wrapper -->

    <div id="right-sidebar-wrapper" class="hide-phone" style="overflow-y:hidden">
        <ul class="sidebar-nav">
            {!! \App\Libraries\HistoryUtils::renderHtml(Auth::user()->account_id) !!}
        </ul>
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
            {!! Form::breadcrumbs((isset($entity) && $entity->exists) ? $entity->present()->statusLabel : false) !!}
          @endif

          @yield('content')
          <br/>
          <div class="row">
            <div class="col-md-12">

              @if (Utils::isNinjaProd())
                @if (Auth::check() && Auth::user()->isTrial())
                  {!! trans(Auth::user()->account->getCountTrialDaysLeft() == 0 ? 'texts.trial_footer_last_day' : 'texts.trial_footer', [
                          'count' => Auth::user()->account->getCountTrialDaysLeft(),
                          'link' => '<a href="javascript:showUpgradeModal()">' . trans('texts.click_here') . '</a>'
                      ]) !!}
                @endif
              @else
                @include('partials.white_label')
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
                @if (Utils::isNinja())
                    <div style="padding-top:20px;padding-bottom:10px;">{{ trans('texts.trial_message') }}</div>
                @endif
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
        <button type="button" class="btn btn-danger" onclick="logout(true)">{{ trans('texts.logout') }}</button>
      </div>
    </div>
  </div>
</div>
@endif

@include('partials.keyboard_shortcuts')

</div>

<p>&nbsp;</p>


@stop
