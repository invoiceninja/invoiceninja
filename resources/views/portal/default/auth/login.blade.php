@extends('portal.default.layouts.guest')

@section('body')
    <body class="app flex-row align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card-group">
                    <div class="card p-4">
                        <div class="card-body">
                            @if (session('info'))
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="alert alert-success alert-dismissible">
                                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                            {{ session('info') }}
                                        </div>
                                    </div>
                                </div>        
                            @elseif (session('error'))
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="alert alert-danger alert-dismissible">
                                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                                            {{ session('error') }}
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('client.login') }}">
                                @csrf
                                <h1>{{ trans('texts.account_login') }}</h1>
                                    @if (Session::has('error'))
                                        <div class="alert alert-danger">
                                            <li>{!! Session::get('error') !!}</li>
                                        </div>
                                    @endif
                                <p class="text-muted"></p>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">
                                          <i class="icon-user"></i>
                                        </span>
                                    </div>
                                    <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" placeholder="{{ trans('texts.email') }}" required autofocus>

                                    @if ($errors->has('email'))
                                        <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('email') }}</strong>
                                        </span>
                                    @endif

                                </div>
                                <div class="input-group mb-4">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">
                                          <i class="icon-lock"></i>
                                        </span>
                                    </div>
                                    <input id="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" placeholder="{{ trans('texts.password') }}" required>

                                    @if ($errors->has('password'))
                                        <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                    @endif
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <button class="btn btn-primary px-4" type="submit">{{ trans('texts.login') }}</button>
                                    </div>
                                    <div class="col-6 text-right">
                                        <a class="btn btn-link" href="{{ route('client.password.request') }}">
                                            {{trans('texts.forgot_password')}}
                                        </a>
                                    </div>
                                </div>
                                
                                
                            </form>
                        </div>
                    </div>
                    @env('hosted')
                    <!--<div class="card text-white bg-primary py-5 d-md-down-none" style="width:44%">
                        <div class="card-body text-center">
                            <div>
                                <h2>trans('texts.sign_up_now')</h2>
                                <p>trans('texts.not_a_member_yet')</p>
                                <a class="btn btn-primary active mt-3" href="{{route('signup') }}">trans('texts.login_create_an_account')</a>
                            </div>
                        </div>
                    </div>
                    -->
                    @endenv
                </div>
            </div>
        </div>
    </div>
    </body>
@endsection


