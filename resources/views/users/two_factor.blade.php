@extends('header')

@section('content')
    @parent


    @if (Utils::isAdmin())
        @include('accounts.nav', ['selected' => ACCOUNT_USER_DETAILS])
    @endif

    {!! Former::open() !!}

    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.two_factor_setup') !!}</h3>
              </div>
                <div class="panel-body form-padding-right">
                    <div class="text-center">
                        <img src="{{ $qrCode }}" alt="">
                        <p class="text-muted">{{ $secret }}</p><br/>
                        <p>{!! trans('texts.two_factor_setup_help', ['link' => link_to('https://github.com/antonioribeiro/google2fa#google-authenticator-apps', 'Google Authenticator', ['target' => '_blank'])]) !!}</p>
                    </div>
                    <p>&nbsp;</p>
                    <center class="buttons">
                        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(url('settings/user_details'))->appendIcon(Icon::create('remove-circle')) !!}
                        {!! Button::success(trans('texts.enable'))->large()->submit()->appendIcon(Icon::create('lock')) !!}
                    </center>
                </div>
            </div>
        </div>
    </div>

    {!! Former::close() !!}

@stop
