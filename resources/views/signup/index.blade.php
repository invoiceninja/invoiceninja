@extends('layouts.master')

@section('body')

<body class="app flex-row align-items-center">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mx-4">
                    <div class="card-body p-4">
                        <span class="align-items-center" style="width:100%; display: block; text-align: center; padding:30px;">
                            <img src="images/logo.png" width="100px" height="100px">
                        </span>
                        <h1 style="text-align: center;">@lang('texts.login_create_an_account')</h1>
                        <p class="text-muted"></p>

                        {{ html()->form('POST', route('signup.submit'))->open() }}

                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">@</span>
                            </div>
                            <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" placeholder="@lang('texts.email')" required autofocus>
                            @if ($errors->has('email'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                            @if ($errors->has('email2'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('email2') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                              <span class="input-group-text">
                                <i class="icon-lock"></i>
                              </span>
                            </div>
                            <input id="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" placeholder="@lang('texts.password')" required>

                            @if ($errors->has('password'))
                                <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <button class="btn btn-block btn-success" type="submit">@lang('texts.create_account')</button>
                    </div>

                    {{ html()->form()->close() }}

                    <div class="card-footer p-4">
                        <div class="row">
                            <div class="col-6">
                                <button class="btn btn-block btn-facebook" type="button">
                                    <span>facebook</span>
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-block btn-twitter" type="button">
                                    <span>twitter</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

</body>


</html>