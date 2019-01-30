@extends('header')

@section('content')
    @parent
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

    {!! Former::open_for_files()->addClass('warn-on-exit')->rules(array(
        'first_name' => 'required',
        'last_name' => 'required',
        'email' => 'email|required',
        'phone' => $user->google_2fa_secret ? 'required' : ''
    )) !!}

    {{ Former::populate($account) }}
    {{ Former::populateField('first_name', $user->first_name) }}
    {{ Former::populateField('last_name', $user->last_name) }}
    {{ Former::populateField('email', $user->email) }}
    {{ Former::populateField('phone', $user->phone) }}
    {{ Former::populateField('signature', $user->signature) }}
    {{ Former::populateField('dark_mode', intval($user->dark_mode)) }}
    {{ Former::populateField('enable_two_factor', $user->google_2fa_secret ? 1 : 0) }}

    @if (Input::has('affiliate'))
        {{ Former::populateField('referral_code', true) }}
    @endif

    @if (Utils::isAdmin())
        @include('accounts.nav', ['selected' => ACCOUNT_USER_DETAILS])
    @endif

    <div class="row">
        <div class="col-md-12">

            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.user_details') !!}</h3>
              </div>
                <div class="panel-body form-padding-right">
                {!! Former::text('first_name') !!}
                {!! Former::text('last_name') !!}
                {!! Former::text('email') !!}
                {!! Former::text('phone') !!}
                {!! Former::file('avatar')
                    ->max(2, 'MB')
                    ->accept('image')
                    ->label(trans('texts.avatar'))
                    ->inlineHelp(trans('texts.logo_help')) !!}

                @if ($user->hasAvatar())
                    <div class="form-group">
                        <div class="col-lg-4 col-sm-4"></div>
                        <div class="col-lg-8 col-sm-8">
                            <a href="{{ $user->getAvatarUrl(true) }}" target="_blank">
                                {!! HTML::image($user->getAvatarUrl(true), 'Logo', ['style' => 'max-width:300px']) !!}
                            </a> &nbsp;
                            <a href="#" onclick="deleteLogo()">{{ trans('texts.remove_avatar') }}</a>
                        </div>
                    </div>
                @endif

                <br/>

                @if (Utils::isOAuthEnabled())
                    {!! Former::plaintext('oneclick_login')->value(
                            $user->oauth_provider_id ?
                                $oauthProviderName . ' - ' . link_to('#', trans('texts.disable'), ['onclick' => 'disableSocialLogin()']) :
                                DropdownButton::primary(trans('texts.enable'))->withContents($oauthLoginUrls)->small()
                        )->help('oneclick_login_help')
                     !!}
                @endif

                @if ($user->confirmed)
                  @if ($user->google_2fa_secret)
                      {!! Former::checkbox('enable_two_factor')
                              ->help(trans('texts.enable_two_factor_help'))
                              ->text(trans('texts.enable'))
                              ->value(1)  !!}
                  @elseif ($user->phone)
                      {!! Former::plaintext('enable_two_factor')->value(
                              Button::primary(trans('texts.enable'))->asLinkTo(url('settings/enable_two_factor'))->small()
                          )->help('enable_two_factor_help') !!}
                  @else
                      {!! Former::plaintext('enable_two_factor')
                          ->value('<span class="text-muted">' . trans('texts.set_phone_for_two_factor') . '</span>') !!}
                  @endif
                @endif

                {!! Former::checkbox('dark_mode')
                        ->help(trans('texts.dark_mode_help'))
                        ->text(trans('texts.enable'))
                        ->value(1)  !!}

                @if (Utils::isNinja())
                    @if ($user->referral_code)
                        {{ Former::setOption('capitalize_translations', false) }}
                        {!! Former::plaintext('referral_code')
                                ->help($referralCounts['free'] . ' ' . trans('texts.free') . ' | ' .
                                    $referralCounts['pro'] . ' ' . trans('texts.pro') .
                                    '<a href="'.REFERRAL_PROGRAM_URL.'" target="_blank" title="'.trans('texts.learn_more').'">' . Icon::create('question-sign') . '</a> ')
                                ->value(NINJA_APP_URL . '/invoice_now?rc=' . $user->referral_code) !!}
                    @else
                        {!! Former::checkbox('referral_code')
                                ->help(trans('texts.referral_code_help'))
                                ->text(trans('texts.enable') . ' <a href="'.REFERRAL_PROGRAM_URL.'" target="_blank" title="'.trans('texts.learn_more').'">' . Icon::create('question-sign') . '</a>')
                                ->value(1)  !!}
                    @endif
                @endif

                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{!! trans('texts.signature') !!}</h3>
                </div>
                <div class="panel-body">
                    {!! Former::textarea('signature')->style('display:none')->raw() !!}
                    <div id="signatureEditor" class="form-control" style="min-height:160px" onclick="focusEditor()"></div>
                    <div class="pull-right" style="padding-top:10px;text-align:right">
                        {!! Button::normal(trans('texts.raw'))->withAttributes(['onclick' => 'showRaw()'])->small() !!}
                    </div>
                    @include('partials/quill_toolbar', ['name' => 'signature'])
                </div>
            </div>

        </div>
    </div>

    @if ( ! Auth::user()->is_admin)
        @include('accounts.partials.notifications')
    @endif

    <center class="buttons">
        @if (Auth::user()->confirmed)
            {!! Button::primary(trans('texts.change_password'))
                    ->appendIcon(Icon::create('lock'))
                    ->large()->withAttributes(['onclick'=>'showChangePassword()']) !!}
        @elseif (Auth::user()->registered && Utils::isNinja())
            {!! Button::primary(trans('texts.resend_confirmation'))
                    ->appendIcon(Icon::create('send'))
                    ->asLinkTo(URL::to('/resend_confirmation'))->large() !!}
        @endif
        {!! Button::success(trans('texts.save'))
                ->submit()->large()
                ->appendIcon(Icon::create('floppy-disk')) !!}
    </center>


    <div class="modal fade" id="rawModal" tabindex="-1" role="dialog" aria-labelledby="rawModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="width:800px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="rawModalLabel">{{ trans('texts.raw_html') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                    <div class="panel panel-default">
                        <div class="modal-body">
                            <textarea id="raw-textarea" rows="20" style="width:100%"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }}</button>
                    <button type="button" onclick="updateRaw()" class="btn btn-success" data-dismiss="modal">{{ trans('texts.update') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="passwordModal" tabindex="-1" role="dialog" aria-labelledby="passwordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="passwordModalLabel">{{ trans('texts.change_password') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                <div class="panel panel-default">
                <div class="panel-body">

                    <div style="background-color: #fff" id="changePasswordDiv" onkeyup="validateChangePassword()" onclick="validateChangePassword()" onkeydown="checkForEnter(event)">
                        &nbsp;

                        {!! Former::password('current_password')->style('width:300px') !!}
                        {!! Former::password('newer_password')->style('width:300px')->label(trans('texts.new_password')) !!}
                        {!! Former::password('confirm_password')->style('width:300px')->help('<span id="passwordStrength">&nbsp;</span>') !!}

                        &nbsp;
                        <br/>
                        <center>
                            <div id="changePasswordError"></div>
                        </center>
                        <br/>
                    </div>

                    <div style="padding-left:40px;padding-right:40px;display:none;min-height:130px" id="working">
                        <h3>{{ trans('texts.working') }}...</h3>
                        <div class="progress progress-striped active">
                            <div class="progress-bar"  role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
                        </div>
                    </div>

                    <div style="background-color: #fff; padding-right:20px;padding-left:20px; display:none" id="successDiv">
                        <br/>
                        <h3>{{ trans('texts.success') }}</h3>
                        {{ trans('texts.updated_password') }}
                        <br/>
                    </div>

                </div>
                </div>
                </div>

                <div class="modal-footer" id="changePasswordFooter">
                    <button type="button" class="btn btn-default" id="cancelChangePasswordButton" data-dismiss="modal">
                        {{ trans('texts.cancel') }}
                        <i class="glyphicon glyphicon-remove-circle"></i>
                    </button>
                    <button type="button" class="btn btn-success" onclick="submitChangePassword()" id="changePasswordButton" disabled>
                        {{ trans('texts.save') }}
                        <i class="glyphicon glyphicon-floppy-disk"></i>
                    </button>
                </div>

            </div>
        </div>
    </div>


    {!! Former::close() !!}



    {!! Form::open(['url' => 'remove_avatar', 'class' => 'removeAvatarForm']) !!}
    {!! Form::close() !!}

    <script type="text/javascript">

        $(function() {
            $('#passwordModal').on('hidden.bs.modal', function () {
                $(['current_password', 'newer_password', 'confirm_password']).each(function(i, field) {
                    var $input = $('form #'+field);
                    $input.val('');
                    $input.closest('div.form-group').removeClass('has-success');
                });
                $('#changePasswordButton').prop('disabled', true);
            })

            $('#passwordModal').on('shown.bs.modal', function () {
                $('#current_password').focus();
           })
        });

        function showChangePassword() {
            $('#passwordModal').modal('show');
        }

        function validateChangePassword(showError)
        {
            var isFormValid = true;
            $(['current_password', 'newer_password', 'confirm_password']).each(function(i, field) {
                var $input = $('form #'+field),
                val = $.trim($input.val());
                var isValid = val;

                if (field != 'current_password') {
                    isValid = val.length >= 8;
                }

                if (isValid && field == 'confirm_password') {
                    isValid = val == $.trim($('#newer_password').val());
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

                if (field == 'newer_password') {
                    var score = scorePassword(val);
                    if (isValid) {
                        isValid = score > 50;
                    }

                    showPasswordStrength(val, score);
                }
            });

            $('#changePasswordButton').prop('disabled', !isFormValid);

            return isFormValid;
        }

        function submitChangePassword()
        {
            if (!validateChangePassword(true)) {
                return;
            }

            $('#changePasswordDiv, #changePasswordFooter').hide();
            $('#working').show();

            $.ajax({
              type: 'POST',
              url: '{{ URL::to('/users/change_password') }}',
              data: 'current_password=' + encodeURIComponent($('form #current_password').val()) +
              '&new_password=' + encodeURIComponent($('form #newer_password').val()) +
              '&confirm_password=' + encodeURIComponent($('form #confirm_password').val()),
              success: function(result) {
                if (result == 'success') {
                  NINJA.formIsChanged = false;
                  $('#changePasswordButton').hide();
                  $('#successDiv').show();
                  $('#cancelChangePasswordButton').html('{{ trans('texts.close') }}');
                } else {
                  $('#changePasswordError').html(result);
                  $('#changePasswordDiv').show();
                }
                $('#changePasswordFooter').show();
                $('#working').hide();
              }
            });
        }

        function disableSocialLogin() {
            sweetConfirm(function() {
                window.location = '{{ URL::to('/auth_unlink') }}';
            });
        }


        var editor = false;
        $(function() {
            editor = new Quill('#signatureEditor', {
                modules: {
                    'toolbar': { container: '#signatureToolbar' },
                    'link-tooltip': true
                },
                theme: 'snow'
            });
            editor.setHTML($('#signature').val());
            editor.on('text-change', function(delta, source) {
                if (source == 'api') {
                    return;
                }
                var html = editor.getHTML();
                $('#signature').val(html);
                NINJA.formIsChanged = true;
            });
        });

        function focusEditor() {
            editor.focus();
        }

        function showRaw() {
            var signature = $('#signature').val();
            $('#raw-textarea').val(formatXml(signature));
            $('#rawModal').modal('show');
        }

        function updateRaw() {
            var value = $('#raw-textarea').val();
            editor.setHTML(value);
            $('#signature').val(value);
        }

        $(function() {
            $('#country_id').combobox();
        });

        function deleteLogo() {
            sweetConfirm(function() {
                $('.removeAvatarForm').submit();
            });
        }

    </script>

@stop

@section('onReady')
    $('#first_name').focus();
@stop
