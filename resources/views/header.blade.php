@extends('master')


@section('head')

  <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>    

  <style type="text/css">

    body {
      background-color: #EEEEEE;
      padding-top: 114px; 
    }

    /* Fix for header covering stuff when the screen is narrower */
    @media screen and (min-width: 1200px) {
      body {
        padding-top: 56px; 
      }
    }

    @media screen and (max-width: 768px) {
      body {
        padding-top: 56px; 
      }
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

  function setTheme(id)
  {
    $('#theme_id').val(id);
    $('form.themeForm').submit();
  }

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

  NINJA.proPlanFeature = '';
  function showProPlan(feature) {
    $('#proPlanModal').modal('show');
    fbq('track', 'InitiateCheckout');
    trackEvent('/account', '/show_pro_plan/' + feature);
    NINJA.proPlanFeature = feature;
  }

  function hideProPlan() {
    $('#proPlanModal').modal('hide');
  }

  function buyProduct(affiliateKey, productId) {
    window.open('{{ Utils::isNinjaDev() ? '' : NINJA_APP_URL }}/license?affiliate_key=' + affiliateKey + '&product_id=' + productId + '&return_url=' + window.location);
  }

  @if (Auth::check() && !Auth::user()->isPro())
  function submitProPlan() {
    fbq('track', 'AddPaymentInfo');
    trackEvent('/account', '/submit_pro_plan/' + NINJA.proPlanFeature);
    if (NINJA.isRegistered) {      
      $.ajax({
        type: 'POST',
        url: '{{ URL::to('account/go_pro') }}',
        success: function(result) { 
          NINJA.formIsChanged = false;
          window.location = '/payment/' + result;
        }
      });     
    } else {
      $('#proPlanModal').modal('hide');    
      $('#go_pro').val('true');
      showSignUp();
    }
  }
  @endif

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

  $(function() {
    window.setTimeout(function() { 
        $(".alert-hide").fadeOut();
    }, 3000);

    $('#search').blur(function(){
      $('#search').css('width', '{{ Utils::isEnglish() ? 150 : 110 }}px');
      $('ul.navbar-right').show();
    });

    $('#search').focus(function(){
      $('#search').css('width', '{{ Utils::isEnglish() ? 264 : 216 }}px');
      $('ul.navbar-right').hide();
      if (!window.hasOwnProperty('searchData')) {
        trackEvent('/activity', '/search');
        $.get('{{ URL::route('getSearchData') }}', function(data) {
          window.searchData = true;
          var datasets = [];
          for (var type in data)
          {
            if (!data.hasOwnProperty(type)) continue;
            datasets.push({
              name: type,
              header: '&nbsp;<b>' + type  + '</b>',
              local: data[type]
            });
          }
          if (datasets.length == 0) {
            return;
          }
          $('#search').typeahead(datasets).on('typeahead:selected', function(element, datum, name) {
            var type = name == 'Contacts' ? 'clients' : name.toLowerCase();
            window.location = '{{ URL::to('/') }}' + '/' + type + '/' + datum.public_id;
          }).focus().typeahead('setQuery', $('#search').val());                         
        });
      }
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

    $('ul.navbar-settings, ul.navbar-history').hover(function () {
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

  });

</script>  

@stop

@section('body')

<nav class="navbar navbar-default navbar-fixed-top" role="navigation">
  <div class="container">

    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-collapse-1">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a href="{{ URL::to(NINJA_WEB_URL) }}" class='navbar-brand' target="_blank">
        <img src="{{ asset('images/invoiceninja-logo.png') }}" style="height:18px;width:auto"/>
      </a>	    
    </div>

    <div class="collapse navbar-collapse" id="navbar-collapse-1">
      <ul class="nav navbar-nav" style="font-weight: bold">
        {!! HTML::nav_link('dashboard', 'dashboard') !!}
        {!! HTML::menu_link('client') !!}
        {!! HTML::menu_link('task') !!}
        {!! HTML::menu_link('invoice') !!}
        {!! HTML::menu_link('payment') !!}
      </ul>

      <div class="navbar-form navbar-right">
        @if (Auth::check())
          @if (!Auth::user()->registered)
            {!! Button::success(trans('texts.sign_up'))->withAttributes(array('id' => 'signUpButton', 'data-toggle'=>'modal', 'data-target'=>'#signUpModal'))->small() !!} &nbsp;
          @elseif (!Auth::user()->isPro())
            {!! Button::success(trans('texts.go_pro'))->withAttributes(array('id' => 'proPlanButton', 'onclick' => 'showProPlan("")'))->small() !!} &nbsp;
          @endif
        @endif

        <div class="btn-group user-dropdown">
          <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
            <div id="myAccountButton" class="ellipsis" style="max-width:100px">
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
                            'logo_path' => isset($item->logo_path) ? $item->logo_path : "",
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
                            'logo_path' => isset($item->logo_path) ? $item->logo_path : "",
                            'selected' => false,
                        ])
                    @endif
                @endforeach
            @else
                @include('user_account', [
                    'account_name' => Auth::user()->account->name ?: trans('texts.untitled'), 
                    'user_name' => Auth::user()->getDisplayName(),
                    'logo_path' => Auth::user()->account->getLogoPath(),
                    'selected' => true,
                ])
            @endif            
            <li class="divider"></li>                
            @if (count(session(SESSION_USER_ACCOUNTS)) > 1)
                <li>{!! link_to('/manage_companies', trans('texts.manage_companies')) !!}</li>
            @elseif (!session(SESSION_USER_ACCOUNTS) || count(session(SESSION_USER_ACCOUNTS)) < 5)
                <li>{!! link_to('/login?new_company=true', trans('texts.add_company')) !!}</li>
            @endif
            <li>{!! link_to('#', trans('texts.logout'), array('onclick'=>'logout()')) !!}</li>
          </ul>
        </div>

      </div>	
      
      <ul class="nav navbar-nav navbar-right navbar-settings"> 
        <li class="dropdown">
          <a href="{{ URL::to('/settings') }}" class="dropdown-toggle">
            <span class="glyphicon glyphicon-cog" title="{{ trans('texts.settings') }}"/>
          </a>
          <ul class="dropdown-menu">
            @foreach (\App\Models\Account::$basicSettings as $setting)
                <li>{!! link_to('settings/' . $setting, uctrans("texts.{$setting}")) !!}</li>
            @endforeach
            <li><a href="{{ url('settings/' . ACCOUNT_INVOICE_SETTINGS) }}">{!! uctrans('texts.advanced_settings') . Utils::getProLabel(ACCOUNT_ADVANCED_SETTINGS) !!}</a></li>
          </ul>
        </li>
      </ul>


      <ul class="nav navbar-nav navbar-right navbar-history"> 
        <li class="dropdown">
          <a href="{{ Utils::getLastURL() }}" class="dropdown-toggle">
            <span class="glyphicon glyphicon-time" title="{{ trans('texts.history') }}"/>
          </a>
          <ul class="dropdown-menu">	        		        	
            @if (count(Session::get(RECENTLY_VIEWED)) == 0)
                <li><a href="#">{{ trans('texts.no_items') }}</a></li>
            @else
                @foreach (Session::get(RECENTLY_VIEWED) as $link)
                    @if (property_exists($link, 'accountId') && $link->accountId == Auth::user()->account_id)
                        <li><a href="{{ $link->url }}">{{ $link->name }}</a></li>	
                    @endif
                @endforeach
            @endif
          </ul>
        </li>
      </ul>

      <form class="navbar-form navbar-right" role="search">
        <div class="form-group">
          <input type="text" id="search" style="width: {{ Utils::isEnglish() ? 150 : 110 }}px;padding-top:0px;padding-bottom:0px" 
            class="form-control" placeholder="{{ trans('texts.search') }}">
        </div>
      </form>


      
      
    </div><!-- /.navbar-collapse -->


  </div>
</nav>

<br/>
<div class="container">
  
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
    {!! HTML::breadcrumbs() !!}
  @endif

  @yield('content')		

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

@if (Auth::check() && !Auth::user()->isPro())
  <div class="modal fade" id="proPlanModal" tabindex="-1" role="dialog" aria-labelledby="proPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog large-dialog">
      <div class="modal-content pro-plan-modal">
        

        <div class="pull-right">
            <img onclick="hideProPlan()" class="close" src="{{ asset('images/pro_plan/close.png') }}"/>
        </div>        
        <div class="row">

            <div class="col-md-7 left-side">
                <center>
                    <h2>{{ trans('texts.pro_plan_title') }}</h2>
                    <img class="img-responsive price" alt="Only $50 Per Year" src="{{ asset('images/pro_plan/price.png') }}"/>
                    <a class="button" href="#" onclick="submitProPlan()">{{ trans('texts.pro_plan_call_to_action') }}</a>
                </center>
            </div>
            <div class="col-md-5">
                <ul>
                    <li>{{ trans('texts.pro_plan_feature1') }}</li>
                    <li>{{ trans('texts.pro_plan_feature2') }}</li>
                    <li>{{ trans('texts.pro_plan_feature3') }}</li>
                    <li>{{ trans('texts.pro_plan_feature4') }}</li>
                    <li>{{ trans('texts.pro_plan_feature5') }}</li>
                    <li>{{ trans('texts.pro_plan_feature6') }}</li>
                    <li>{{ trans('texts.pro_plan_feature7') }}</li>
                    <li>{{ trans('texts.pro_plan_feature8') }}</li>
                </ul>
            </div>
        </div>

      </div>
    </div>
  </div>


@endif

{{-- Per our license, please do not remove or modify this section. --}}
@if (!Utils::isNinjaProd())
</div>
<p>&nbsp;</p>
<div class="container">
  {{ trans('texts.powered_by') }} <a href="https://www.invoiceninja.com/?utm_source=powered_by" target="_blank">InvoiceNinja.com</a> -
  {!! link_to(RELEASES_URL, 'v' . NINJA_VERSION, ['target' => '_blank']) !!} | 
  @if (Auth::user()->account->isWhiteLabel())  
    {{ trans('texts.white_labeled') }}
  @else
    <a href="#" onclick="$('#whiteLabelModal').modal('show');">{{ trans('texts.white_label_link') }}</a>

    <div class="modal fade" id="whiteLabelModal" tabindex="-1" role="dialog" aria-labelledby="whiteLabelModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h4 class="modal-title" id="myModalLabel">{{ trans('texts.white_label_header') }}</h4>
          </div>

          <div class="panel-body">
            <p>{{ trans('texts.white_label_text')}}</p>
            <div class="row">
                <div class="col-md-6">
                    <h4>{{ trans('texts.before') }}</h4>
                    {!! HTML::image('images/pro_plan/white_label_before.png', 'before', ['width' => '100%']) !!}
                </div>
                <div class="col-md-6">
                    <h4>{{ trans('texts.after') }}</h4>
                    {!! HTML::image('images/pro_plan/white_label_after.png', 'after', ['width' => '100%']) !!}
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

<p>&nbsp;</p>


@stop