@extends('layouts.guest')

@section('body')

<body class="app flex-row align-items-center">

    <div class="container" id="signup">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mx-4">
                    <div class="card-body p-4">
                        <span class="align-items-center" style="width:100%; display: block; text-align: center; padding:30px;">
                            <img src="images/logo.png" width="100px" height="100px">
                        </span>
                        <h1 style="text-align: center;">@lang('texts.login_create_an_account')</h1>
                        <p class="text-muted"></p>

                            <form method="POST" action="{{ route('signup.submit')}}">
                            @csrf
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="icon-user"></i>
                                </span>
                            </div>
                            <input id="first_name" type="text" class="form-control{{ $errors->has('first_name') ? ' is-invalid' : '' }}" name="first_name" value="{{ old('first_name') }}" placeholder="@lang('texts.first_name')" required autofocus>
                            @if ($errors->has('first_name'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('first_name') }}</strong>
                                </span>
                            @endif
                        </div>


                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="icon-user"></i>
                                </span>
                            </div>
                            <input id="last_name" type="text" class="form-control{{ $errors->has('last_name') ? ' is-invalid' : '' }}" name="last_name" value="{{ old('last_name') }}" placeholder="@lang('texts.last_name')" required autofocus>
                            @if ($errors->has('last_name'))
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $errors->first('last_name') }}</strong>
                                </span>
                            @endif
                        </div>


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
                            <input id="password" type="password" class="form-control" {{ $errors->has('password') ? ' is-invalid' : '' }} name="password" placeholder="@lang('texts.password')" required>

                            @if ($errors->has('password'))
                                <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <div class="form-check" style="margin-top:10px; margin-bottom: 10px;">
                            <input class="form-check-input" type="checkbox" id="terms_of_service" name="terms_of_service" value="1" v-model="checked1" {{(old('terms_of_service') == "1") ? 'checked': ''}}>
                            <label class="form-check-label" for="terms_of_service">
                                @lang('texts.agree_to_terms', ['terms' => ''])<a href=" {{config('ninja.terms_of_service_url.' . config('ninja.environment')) }}" target="_blank">@lang('texts.terms_of_service')</a>

                            </label>
                        </div>

                        <div class="form-check" style="margin-top:10px; margin-bottom: 10px;">
                            <input class="form-check-input" type="checkbox" id="privacy_policy" name="privacy_policy" value="1" v-model="checked2" {{(old('privacy_policy') == "1") ? 'checked': ''}}>
                            <label class="form-check-label" for="privacy_policy">
                                @lang('texts.agree_to_terms', ['terms' => ''])<a href=" {{config('ninja.privacy_policy_url.' . config('ninja.environment')) }}" target="_blank">@lang('texts.privacy_policy')</a>

                            </label>
                        </div>

                        <button class="btn btn-block btn-success" type="submit" :disabled="!isDisabled">@lang('texts.create_account')</button>
                    </div>

                    </form>
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

<script src="https://cdn.jsdelivr.net/npm/vue@2.6.6/dist/vue.js"></script>

<script>
    new Vue({
        el : '#signup',
        data: {
            checked1 : false,
            checked2 : false
        },
        computed: {
            isDisabled: function(){
                return (this.checked1 && this.checked2);
            }
        }
    });
</script>

@endsection

</body>

</html>