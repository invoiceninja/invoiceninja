<script type="text/javascript">

  $(function() {

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

      @if (Auth::check() && !Utils::isNinja() && ! Auth::user()->registered)
        $('#closeSignUpButton').hide();
        showSignUp();
      @elseif(Session::get('sign_up') || Input::get('sign_up'))
        showSignUp();
      @endif

      // Ensure terms is checked for sign up form
      @if (Auth::check() && ! Auth::user()->registered)
          setSignupEnabled(false);
          $("#terms_checkbox").change(function() {
              setSignupEnabled(this.checked);
          });
      @endif

  });


  function showSignUp() {
    $('#signUpModal').modal('show');
  }

  function hideSignUp() {
    $('#signUpModal').modal('hide');
  }

  function setSignupEnabled(enabled) {
    $('.signup-form input[type=text]').prop('disabled', !enabled);
    $('.signup-form input[type=password]').prop('disabled', !enabled);
    if (enabled) {
        $('.signup-form a.btn').removeClass('disabled');
    } else {
        $('.signup-form a.btn').addClass('disabled');
    }
  }

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

    @if (! Auth::user()->registered)
        if (!$('#terms_checkbox').is(':checked')) {
          isFormValid = false;
        }
    @endif

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
          @if (Auth::user()->registered)
              hideSignUp();
              NINJA.formIsChanged = false;
              location.reload();
          @else
              handleSignedUp();
              NINJA.isRegistered = true;
              $('#signUpButton').hide();
              $('#myAccountButton').html(result);
              $('#signUpSuccessDiv, #signUpFooter, #closeSignUpButton').show();
              $('#working, #saveSignUpButton').hide();
          @endif
        }
      }
    });
  }

  function handleSignedUp() {
      if (isStorageSupported()) {
          localStorage.setItem('guest_key', '');
      }
      fbq('track', 'CompleteRegistration');
      trackEvent('/account', '/signed_up');
  }

</script>

<div class="modal fade" id="signUpModal" tabindex="-1" role="dialog" aria-labelledby="signUpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title" id="myModalLabel">{{ Auth::user()->registered ? trans('texts.add_company') : trans('texts.sign_up') }}</h4>
      </div>

      <div class="container" style="width: 100%; padding-bottom: 0px !important">
      <div class="panel panel-default">
      <div class="panel-body">

      <div id="signUpDiv" onkeyup="validateSignUp()" onclick="validateSignUp()" onkeydown="checkForEnter(event)">
        {!! Former::open('signup/submit')->addClass('signUpForm')->autocomplete('off') !!}

        @if (Auth::check() && ! Auth::user()->registered)
            {!! Former::populateField('new_first_name', Auth::user()->first_name) !!}
            {!! Former::populateField('new_last_name', Auth::user()->last_name) !!}
            {!! Former::populateField('new_email', Auth::user()->email) !!}
        @endif

        <div style="display:none">
          {!! Former::text('path')->value(Request::path()) !!}
          {!! Former::text('go_pro') !!}
        </div>

        <div class="row signup-form">
            @if (! Auth::user()->registered)
                <div class="col-md-12">
                    {!! Former::checkbox('terms_checkbox')
                        ->label(' ')
                        ->value(1)
                        ->text(trans('texts.agree_to_terms', ['terms' => '<a href="'.Utils::getTermsLink().'" target="_blank">'.trans('texts.terms_of_service').'</a>']))
                        ->raw() !!}
                    <br/>
                </div>
                <br/>&nbsp;<br/>
            @endif
            @if (Utils::isOAuthEnabled() && ! Auth::user()->registered)
                <div class="col-md-5">
                    @foreach (App\Services\AuthService::$providers as $provider)
                    <a href="{{ URL::to('auth/' . $provider) }}" class="btn btn-primary btn-block"
                        style="padding-top:10px;padding-bottom:10px;margin-top:10px;margin-bottom:10px"
                        id="{{ strtolower($provider) }}LoginButton">
                        <i class="fa fa-{{ strtolower($provider) }}"></i> &nbsp;
                        {{ $provider }}
                    </a>
                    @endforeach
                </div>
                <div class="col-md-1">
                    <div style="border-right:thin solid #CCCCCC;height:90px;width:8px;margin-bottom:10px;"></div>
                    {{ trans('texts.or') }}
                    <div style="border-right:thin solid #CCCCCC;height:90px;width:8px;margin-top:10px;"></div>
                </div>
                <div class="col-md-6">
            @else
                <div class="col-md-12">
            @endif
                {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 0) }}
                {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 0) }}

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
                        ->autocomplete('new-password')
                        ->label(' ') !!}

                {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
                {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}
            </div>

            <center><div id="errorTaken" style="display:none">&nbsp;<br/><b>{{ trans('texts.email_taken') }}</b></div></center>

            <div class="col-md-12">
                @if (Auth::user()->registered)
                    <div style="padding-top:20px;padding-bottom:10px;">{!! trans('texts.email_alias_message') !!}</div>
                @elseif (Utils::isNinja())
                    <div style="padding-top:20px;padding-bottom:10px;">{{ trans('texts.trial_message') }}</div>
                @endif
            </div>
        </div>

        {!! Former::close() !!}

      </div>

      <div style="padding-left:40px;padding-right:40px;display:none;min-height:130px" id="working">
        <h3>{{ trans('texts.working') }}...</h3>
        <div class="progress progress-striped active">
          <div class="progress-bar"  role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
        </div>
      </div>

      <div style="background-color: #fff; padding-right:20px;padding-left:20px; display:none" id="signUpSuccessDiv">
        <h3>{{ trans('texts.success') }}</h3>
        <br/>
        @if (Utils::isNinja())
          {{ trans('texts.success_message') }}
        @endif
        <br/>
      </div>

      </div>
      </div>

      <div class="modal-footer" style="margin-top: 0px;padding-right:0px">
        <span id="signUpFooter">
            <button type="button" class="btn btn-default" id="closeSignUpButton" data-dismiss="modal">{{ trans('texts.close') }} <i class="glyphicon glyphicon-remove-circle"></i></button>
            <button type="button" class="btn btn-primary" id="saveSignUpButton" onclick="validateServerSignUp()" disabled>{{ trans('texts.save') }} <i class="glyphicon glyphicon-floppy-disk"></i></button>
        </span>
      </div>
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

      <div class="container" style="width: 100%; padding-bottom: 0px !important">
      <div class="panel panel-default">
      <div class="panel-body">
        <h3>{{ trans('texts.are_you_sure') }}</h3><br/>
        <p>{{ trans('texts.erase_data') }}</p>
      </div>
      </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
        <button type="button" class="btn btn-danger" onclick="logout(true)">{{ trans('texts.logout_and_delete') }}</button>
      </div>
    </div>
  </div>
</div>
