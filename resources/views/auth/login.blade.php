@extends('login')

@section('form')

    @include('partials.warn_session', ['redirectTo' => '/logout?reason=inactive'])

    <div class="container">

        {!! Former::open('login')
                ->rules(['email' => 'required|email', 'password' => 'required'])
                ->addClass('form-signin') !!}

        <h2 class="form-signin-heading">{{ trans('texts.account_login') }}</h2>
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
                    <div class="col-md-3 col-xs-6">
                        <a href="{{ URL::to('auth/' . $provider) }}" class="btn btn-primary btn-lg" title="{{ $provider }}"
                           id="{{ strtolower($provider) }}LoginButton">
                            @if($provider == SOCIAL_GITHUB)
                                <i class="fa fa-github-alt"></i>
                            @else
                                <i class="fa fa-{{ strtolower($provider) }}"></i>
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
                    {!! link_to(NINJA_WEB_URL.'/knowledgebase/', trans('texts.knowledge_base'), ['target' => '_blank']) !!}
                </div>
            @endif
        </div>
        {!! Former::close() !!}

        @if(Utils::allowNewAccounts())
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
        })
    </script>

@endsection
