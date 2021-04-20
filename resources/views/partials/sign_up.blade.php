<script type="text/javascript">

$(function() {

    validateSignUp();

    $('#signUpModal').on('shown.bs.modal', function () {
        trackEvent('/account', '/view_sign_up');
        // change the type after page load to prevent errors in Chrome console
        $('#new_password').attr('type', 'password');
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
    @if (Auth::check())
    setSignupEnabled(false);
    $("#terms_checkbox, #privacy_checkbox").change(function() {
        setSignupEnabled($('#terms_checkbox').is(':checked') && $('#privacy_checkbox').is(':checked'));
    });
    @endif

});


function showSignUp() {
    if (location.href.indexOf('/dashboard') == -1) {
        location.href = "{{ url('/dashboard') }}?sign_up=true";
    } else {
        $('#signUpModal').modal('show');
    }
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

function validateSignUp(showError) {
    var isFormValid = true;
    $(['first_name','last_name','email','password']).each(function(i, field) {
        var $input = $('form.signUpForm #new_'+field),
        val = $.trim($input.val());
        var isValid = val && val.length >= (field == 'password' ? 8 : 1);

        if (field == 'password') {
            var score = scorePassword(val);
            if (isValid) {
                isValid = score > 50;
            }

            showPasswordStrength(val, score);
        }

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

    if (! $('#terms_checkbox').is(':checked') || ! $('#privacy_checkbox').is(':checked')) {
        isFormValid = false;
    }

    $('#saveSignUpButton').prop('disabled', !isFormValid);

    return isFormValid;
}

function validateServerSignUp() {
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
                location.href = "{{ url('/dashboard') }}";
                @else
                handleSignedUp();
                NINJA.isRegistered = true;
                $('#gettingStartedIframe').attr('src', '{{ str_replace('watch?v=', 'embed/', config('ninja.video_urls.getting_started')) }}');
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

@if (\Request::is('dashboard'))
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
            <div class="col-md-12">
                {!! Former::checkbox('terms_checkbox')
                    ->label(' ')
                    ->value(1)
                    ->text(trans('texts.agree_to_terms', [
                        'terms' => link_to(config('ninja.terms_of_service_url.' . (Utils::isSelfHost() ? 'selfhost' : 'hosted')), trans('texts.terms_of_service'), ['target' => '_blank']),
                    ]))
                    ->raw() !!}
                    {!! Former::checkbox('privacy_checkbox')
                        ->label(' ')
                        ->value(1)
                        ->text(trans('texts.agree_to_terms', [
                            'terms' => link_to(config('ninja.privacy_policy_url.' . (Utils::isSelfHost() ? 'selfhost' : 'hosted')), trans('texts.privacy_policy'), ['target' => '_blank']),
                        ]))
                        ->raw() !!}
                <br/>
            </div>
            <br/>&nbsp;<br/>
            @if (Utils::isOAuthEnabled() && ! Auth::user()->registered)
                <div class="col-md-6">
                    @foreach (App\Services\AuthService::$providers as $provider)
                    <a href="{{ URL::to('auth/' . $provider) }}" class=""
                        style="padding-top:10px;padding-bottom:10px;margin-top:10px;margin-bottom:10px"
                        id="{{ strtolower($provider) }}LoginButton">
                            @if($provider == SOCIAL_GITHUB)
                                <img style="height: 6rem;" src="{{ asset('images/btn_github_signin.png') }}">
                            @elseif($provider == SOCIAL_GOOGLE)
                                <img style="height: 6rem;" src="{{ asset('images/btn_google_signin_dark_normal_web@2x.png') }}">
                            @elseif($provider == SOCIAL_LINKEDIN)
                                <img style="height: 6rem;" src="{{ asset('images/btn_linkedin_signin.png') }}">
                            @elseif($provider === SOCIAL_FACEBOOK)
                                <img style="height: 6rem;" src="{{ asset('images/btn_facebook_signin.png') }}">
                            @endif
                    </a>
                    @endforeach
                </div>
                <div class="col-md-1">
                    <div style="border-right:thin solid #CCCCCC;height:90px;width:8px;margin-bottom:10px;"></div>
                    {{ trans('texts.or') }}
                    <div style="border-right:thin solid #CCCCCC;height:90px;width:8px;margin-top:10px;"></div>
                </div>
                <div class="col-md-5">
            @else
                <div class="col-md-12">
            @endif
                {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 0) }}
                {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 0) }}

                {!! Former::text('new_first_name')
                        ->placeholder(trans('texts.first_name'))
                        ->autocomplete('given-name')
                        ->data_lpignore('true')
                        ->label(' ') !!}
                {!! Former::text('new_last_name')
                        ->placeholder(trans('texts.last_name'))
                        ->autocomplete('family-name')
                        ->data_lpignore('true')
                        ->label(' ') !!}
                {!! Former::text('new_email')
                        ->placeholder(trans('texts.email'))
                        ->autocomplete('email')
                        ->data_lpignore('true')
                        ->label(' ') !!}
                {!! Former::text('new_password')
                        ->placeholder(trans('texts.password'))
                        ->autocomplete('new-password')
                        ->data_lpignore('true')
                        ->label(' ')
                        ->help('<span id="passwordStrength">&nbsp;</span>') !!}

                {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
                {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}
            </div>

            <center><div id="errorTaken" style="display:none">&nbsp;<br/><b>{{ trans('texts.email_taken') }}</b></div></center>

            <div class="col-md-12">
                <div style="padding-top:20px;padding-bottom:10px;">
                    @if (Auth::user()->registered)
                        {!! trans('texts.email_alias_message') !!}
                    @elseif (Utils::isNinjaProd())
                        @if (Utils::isPro())
                            {{ trans('texts.free_year_message') }}
                        @else
                            {{ trans('texts.trial_message') }}
                        @endif
                    @endif
                </div>
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
          <br/>&nbsp;<br/>
        @endif
        @if (! auth()->user()->registered)
            <iframe id="gettingStartedIframe" width="100%" height="315"></iframe>
        @endif
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
@endif

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
