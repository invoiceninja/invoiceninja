@extends('login')

@section('form')

    @include('partials.warn_session', ['redirectTo' => '/logout?reason=inactive'])

    <div class="container">

        {!! Former::open('login')
                ->rules(['email' => 'required|email', 'password' => 'required'])
                ->addClass('form-signin') !!}

        <h2 class="form-signin-heading">
            @if (strstr(session('url.intended'), 'time_tracker'))
                {{ trans('texts.time_tracker_login') }}
            @else
                {{ trans('texts.account_login') }}
            @endif
        </h2>
        <hr class="green">

        @if (count($errors->all()))
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </div>
        @endif

        @if (Session::has('warning'))
            <div class="alert alert-warning">{!! Session::get('warning') !!}</div>
        @endif

        @if (Session::has('message'))
            <div class="alert alert-info">{!! Session::get('message') !!}</div>
        @endif

        @if (Session::has('error'))
            <div class="alert alert-danger">
                <li>{!! Session::get('error') !!}</li>
            </div>
        @endif

        @if (env('REMEMBER_ME_ENABLED'))
            {{ Former::populateField('remember', 'true') }}
            {!! Former::hidden('remember')->raw() !!}
        @endif

        <div>
            {!! Former::text('email')->placeholder(trans('texts.email_address'))->raw() !!}
            {!! Former::password('password')->placeholder(trans('texts.password'))->raw() !!}
        </div>

        {!! Button::success(trans('texts.login'))
                    ->withAttributes(['id' => 'loginButton', 'class' => 'green'])
                    ->large()->submit()->block() !!}

        @if (Utils::isOAuthEnabled())
            <div class="row existing-accounts">
                <p>{{ trans('texts.login_or_existing') }}</p>
                @foreach (App\Services\AuthService::$providers as $provider)
                    <div class="col-md-12">
                        <a href="{{ URL::to('auth/' . $provider) }}" title="{{ $provider }}"
                           id="{{ strtolower($provider) }}LoginButton">
                            @if($provider == SOCIAL_GITHUB)
                                <img style="height: 6rem;" src="{{ asset('images/social/signin/btn_github_signin.png') }}">
                            @elseif($provider == SOCIAL_GOOGLE)
                                <img style="height: 6rem;" src="{{ asset('images/social/signin/btn_google_signin_dark_normal_web@2x.png') }}">
                            @elseif($provider == SOCIAL_LINKEDIN)
                                <img style="height: 6rem;" src="{{ asset('images/social/signin/btn_linkedin_signin.png') }}">
                            @elseif($provider === SOCIAL_FACEBOOK)
                                <img style="height: 6rem;" src="{{ asset('images/social/signin/btn_facebook_signin.png') }}">
                            @endif
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="row meta">
            @if (Utils::isWhiteLabel())
                <center>
                    <br/>{!! link_to('/recover_password', trans('texts.recover_password')) !!}
                </center>
            @else
                <div class="col-md-7 col-sm-12">
                    {!! link_to('/recover_password', trans('texts.recover_password')) !!}
                </div>
                <div class="col-md-5 col-sm-12">
                    @if (Utils::isTimeTracker())
                        {!! link_to('#', trans('texts.self_host_login'), ['onclick' => 'setSelfHostUrl()']) !!}
                    @else
                        {!! link_to(NINJA_WEB_URL.'/knowledge-base/', trans('texts.knowledge_base'), ['target' => '_blank']) !!}
                    @endif
                </div>
            @endif
        </div>
        {!! Former::close() !!}

        @if (Utils::allowNewAccounts() && ! strstr(session('url.intended'), 'time_tracker'))
            <div class="row sign-up">
                <div class="col-md-3 col-md-offset-3 col-xs-12">
                    <h3>{{trans('texts.not_a_member_yet')}}</h3>
                    <p>{{trans('texts.login_create_an_account')}}</p>
                </div>
                <div class="col-md-3 col-xs-12">
                    {!! Button::primary(trans('texts.sign_up_now'))->asLinkTo(URL::to('/invoice_now?sign_up=true'))->withAttributes(['class' => 'blue'])->large()->submit()->block() !!}
                </div>
            </div>
        @endif
    </div>


    <script type="text/javascript">
        $(function() {
            if ($('#email').val()) {
                $('#password').focus();
            } else {
                $('#email').focus();
            }

            @if (Utils::isTimeTracker())
                if (isStorageSupported()) {
                    var selfHostUrl = localStorage.getItem('last:time_tracker:url');
                    if (selfHostUrl) {
                        location.href = selfHostUrl;
                        return;
                    }
                    $('#email').change(function() {
                        localStorage.setItem('last:time_tracker:email', $('#email').val());
                    })
                    var email = localStorage.getItem('last:time_tracker:email');
                    if (email) {
                        $('#email').val(email);
                        $('#password').focus();
                    }
                }
            @endif
        })

        @if (Utils::isTimeTracker())
            function setSelfHostUrl() {
                if (! isStorageSupported()) {
                    swal("{{ trans('texts.local_storage_required') }}");
                    return;
                }
                swal({
                    title: "{{ trans('texts.set_self_hoat_url') }}",
                    input: 'text',
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                }).then(function (value) {
                    if (! value || value.indexOf('http') !== 0) {
                        swal("{{ trans('texts.invalid_url') }}")
                        return;
                    }
                    value = value.replace(/\/+$/, '') + '/time_tracker';
                    localStorage.setItem('last:time_tracker:url', value);
                    location.reload();
                }).catch(swal.noop);
            }
        @endif

    </script>

@endsection
