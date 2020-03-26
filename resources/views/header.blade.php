@extends('master')

@section('head_css')
    <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

    @if (Utils::isNinjaDev())
        <style type="text/css">
            .nav-footer {
                @if (config('mail.driver') == 'log' && ! config('services.postmark'))
                    background-color: #50C878 !important;
                @else
                    background-color: #FD6A02 !important;
                @endif
            }
        </style>
    @endif
@stop

@section('head')

<script type="text/javascript">

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
      window.location = '{{ URL::to('logout') }}' + (force ? '?force_logout=true' : '');
    } else {
      $('#logoutModal').modal('show');
    }
  }

  function hideMessage() {
    $('.alert-info').fadeOut();
    $.get('/hide_message', function(response) {
      console.log('Reponse: %s', response);
    });
  }

  function openTimeTracker() {
      var width = 1060;
      var height = 700;
      var left = (screen.width/2)-(width/4);
      var top = (screen.height/2)-(height/1.5);
      window.open("{{ url('/time_tracker') }}", "time-tracker", "width="+width+",height="+height+",scrollbars=no,toolbar=no,screenx="+left+",screeny="+top+",location=no,titlebar=no,directories=no,status=no,menubar=no");
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
          @if (Auth::check() && Auth::user()->account->customLabel('client1'))
          ,{
            name: 'data',
            limit: 3,
            display: 'value',
            source: searchData(data['{{ Auth::user()->account->present()->customLabel('client1') }}'], 'tokens'),
            templates: {
              header: '&nbsp;<span style="font-weight:600;font-size:16px">{{ Auth::user()->account->present()->customLabel('client1') }}</span>'
            }
          }
          @endif
          @if (Auth::check() && Auth::user()->account->customLabel('client2'))
          ,{
            name: 'data',
            limit: 3,
            display: 'value',
            source: searchData(data['{{ Auth::user()->account->present()->customLabel('client2') }}'], 'tokens'),
            templates: {
              header: '&nbsp;<span style="font-weight:600;font-size:16px">{{ Auth::user()->account->present()->customLabel('client2') }}</span>'
            }
          }
          @endif
          @if (Auth::check() && Auth::user()->account->customLabel('invoice_text1'))
          ,{
            name: 'data',
            limit: 3,
            display: 'value',
            source: searchData(data['{{ Auth::user()->account->present()->customLabel('invoice_text1') }}'], 'tokens'),
            templates: {
              header: '&nbsp;<span style="font-weight:600;font-size:16px">{{ Auth::user()->account->present()->customLabel('invoice_text1') }}</span>'
            }
          }
          @endif
          @if (Auth::check() && Auth::user()->account->customLabel('invoice_text2'))
          ,{
            name: 'data',
            limit: 3,
            display: 'value',
            source: searchData(data['{{ Auth::user()->account->present()->customLabel('invoice_text2') }}'], 'tokens'),
            templates: {
              header: '&nbsp;<span style="font-weight:600;font-size:16px">{{ Auth::user()->account->present()->customLabel('invoice_text2') }}</span>'
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
    // auto-logout after 8 hours
    window.setTimeout(function() {
        window.location = '{{ URL::to('/logout?reason=inactive') }}';
    }, {{ 1000 * env('AUTO_LOGOUT_SECONDS', (60 * 60 * 8)) }});

    // auto-hide status alerts
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

    $('ul.navbar-settings, ul.navbar-search').hover(function () {
        if ($('.user-accounts').css('display') == 'block') {
            $('.user-accounts').dropdown('toggle');
        }
    });

    @yield('onReady')

    @if (Input::has('focus'))
        $('#{{ Input::get('focus') }}').focus();
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
        if (isStorageSupported() && /\/settings\//.test(location.href)) {
            var target = $(e.target).attr("href") // activated tab
            if (history.pushState) {
                history.pushState(null, null, target);
            }
            if (isStorageSupported()) {
                localStorage.setItem('last:settings_page', location.href.replace(location.hash, ''));
            }
        }
    });

    // set timeout onDomReady
    setTimeout(delayedFragmentTargetOffset, 500);

    // add scroll offset to fragment target (if there is one)
    function delayedFragmentTargetOffset(){
        var offset = $(':target').offset();
        if (offset) {
            var scrollto = offset.top - 180; // minus fixed header height
            $('html, body').animate({scrollTop:scrollto}, 0);
        }
    }

  });

</script>

@stop

@section('body')

@if (Utils::isNinjaProd() && ! Request::is('settings/account_management'))
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
              @if (!Auth::user()->confirmed)
                {!! Button::success(trans('texts.sign_up'))->withAttributes(array('id' => 'signUpButton', 'onclick' => 'showSignUp()', 'style' => 'max-width:100px;;overflow:hidden'))->small() !!} &nbsp;
              @endif
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
            @if (Utils::isAdmin() && Auth::user()->confirmed && Utils::getResllerType() != RESELLER_ACCOUNT_COUNT)
              @if (!session(SESSION_USER_ACCOUNTS) || count(session(SESSION_USER_ACCOUNTS)) < 5)
                  <li>{!! link_to('#', trans('texts.add_company'), ['onclick' => 'showSignUp()']) !!}</li>
              @endif
            @endif
            <li>{!! link_to('#', trans('texts.logout'), array('onclick'=>'logout()')) !!}</li>
          </ul>
        </div>

      </div>

      {!! Former::open('/handle_command')->id('search-form')->addClass('navbar-form navbar-right')->role('search') !!}
        <div class="form-group has-feedback">
          <input type="text" name="command" id="search" style="width: 280px;padding-top:0px;padding-bottom:0px;margin-right:12px;"
            class="form-control" placeholder="{{ trans('texts.search') . ': ' . trans('texts.search_hotkey')}}"/>
            @if (env('SPEECH_ENABLED'))
                @include('partials/speech_recognition')
            @endif
        </div>
      {!! Former::close() !!}

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
            'proposals' => false,
            'projects' => false,
            'tasks' => false,
            'expenses' => false,
            'vendors' => false,
            'reports' => false,
            'settings' => false,
        ] as $key => $value)
            {!! Form::nav_link($key, $value ?: $key) !!}
        @endforeach
      </ul>
    </div><!-- /.navbar-collapse -->

</nav>

<div id="wrapper" class='{{ session(SESSION_LEFT_SIDEBAR) ? 'toggled-left' : '' }} {{ session(SESSION_RIGHT_SIDEBAR, true) ? 'toggled-right' : '' }}'>

    <!-- Sidebar -->
    <div id="left-sidebar-wrapper" class="hide-phone">
        <ul class="sidebar-nav {{ Auth::user()->dark_mode ? 'sidebar-nav-dark' : 'sidebar-nav-light' }}">
            @foreach([
                'dashboard',
                'clients',
                'products',
                'invoices',
                'payments',
                'recurring_invoices',
                'credits',
                'quotes',
                'proposals',
                'projects',
                'tasks',
                'expenses',
                'vendors',
            ] as $option)
                @if(!Auth::user()->account->isModuleEnabled(substr($option, 0, -1)))
                    {{ '' }}
                @else
                    @include('partials.navigation_option')
                @endif
            @endforeach
            @if ( ! Utils::isNinjaProd())
                @foreach (Module::collections() as $module)
                    @includeWhen(empty($module->get('no-sidebar')) || $module->get('no-sidebar') != '1', 'partials.navigation_option', [
                        'option' => $module->getAlias(),
                        'icon' => $module->get('icon', 'th-large'),
                    ])
                @endforeach
            @endif
            @if (Auth::user()->hasPermission('view_reports'))
                @include('partials.navigation_option', ['option' => 'reports'])
            @endif
            @include('partials.navigation_option', ['option' => 'settings'])
            <li style="width:100%;">
                <div class="nav-footer">
                    @if (Auth::user()->registered)
                        <a href="javascript:showContactUs()" title="{{ trans('texts.contact_us') }}">
                            <i class="fa fa-envelope"></i>
                        </a>
                    @endif
                    <a href="{{ url(NINJA_FORUM_URL) }}" target="_blank" title="{{ trans('texts.support_forum') }}">
                        <i class="fa fa-list-ul"></i>
                    </a>
                    <a href="javascript:showKeyboardShortcuts()" title="{{ trans('texts.help') }}">
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
        <ul class="sidebar-nav {{ Auth::user()->dark_mode ? 'sidebar-nav-dark' : 'sidebar-nav-light' }}">
            {!! \App\Libraries\HistoryUtils::renderHtml(Auth::user()->account_id) !!}
        </ul>
    </div>

    <!-- Page Content -->
    <div id="page-content-wrapper">
        <div class="container-fluid">

          @include('partials.warn_session', ['redirectTo' => '/dashboard'])

          @if (Session::has('warning'))
            <div class="alert alert-warning">{!! Session::get('warning') !!}</div>
          @elseif (env('WARNING_MESSAGE'))
            <div class="alert alert-warning">{!! env('WARNING_MESSAGE') !!}</div>
          @endif

          @if (Session::has('message'))
            <div class="alert alert-info alert-hide" style="z-index:9999">
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

          <div class="pull-right">
              @yield('top-right')
          </div>

          @if (!isset($showBreadcrumbs) || $showBreadcrumbs)
            {!! Form::breadcrumbs((! empty($entity) && $entity->exists && !$entity->deleted_at) ? $entity->present()->statusLabel : false) !!}
          @endif

          @yield('content')
          <br/>
          <div class="row">
            <div class="col-md-12">

              @if (Utils::isNinjaProd())
                @if (Auth::check() && Auth::user()->hasActivePromo())
                    {!! trans('texts.promotion_footer', [
                            'link' => '<a href="javascript:showUpgradeModal()">' . trans('texts.click_here') . '</a>'
                        ]) !!}
                @elseif (Auth::check() && Auth::user()->isTrial())
                  {!! trans(Auth::user()->account->getCountTrialDaysLeft() == 0 ? 'texts.trial_footer_last_day' : 'texts.trial_footer', [
                          'count' => Auth::user()->account->getCountTrialDaysLeft(),
                          'link' => '<a href="javascript:showUpgradeModal()">' . trans('texts.click_here') . '</a>'
                      ]) !!}
                @endif
              @else
                @include('partials.white_label', ['company' => Auth::user()->account->company])
              @endif
            </div>
        </div>
    </div>
    <!-- /#page-content-wrapper -->
</div>

@include('partials.contact_us')
@include('partials.sign_up')
@include('partials.keyboard_shortcuts')

@if (auth()->check() && auth()->user()->registered && ! auth()->user()->hasAcceptedLatestTerms())
    @include('partials.accept_terms')
@endif

</div>

<p>&nbsp;</p>


@stop
