@extends('public.header')

@section('head')	
@parent
<style type="text/css">
    body {
        padding-top: 40px;
        padding-bottom: 40px;
    }
    .modal-header {
        border-top-left-radius: 3px;
        border-top-right-radius: 3px;
        background:#222;
        color:#fff
    }
    .modal-header h4 {
        margin:0;
    }
    .modal-header img {
        float: left; 
        margin-right: 20px;
    }
    .form-signin {
        max-width: 400px;
        margin: 0 auto;
        background: #fff;
    }
    p.link a {
        font-size: 11px;
    }
    .form-signin .inner {
        padding: 20px;
        border-bottom-right-radius: 3px;
        border-bottom-left-radius: 3px;
        border-left: 1px solid #ddd;
        border-right: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
    }
    .form-signin .checkbox {
        font-weight: normal;
    }
    .form-signin .form-control {
        margin-bottom: 17px !important;
    }
    .form-signin .form-control:focus {
        z-index: 2;
    }

    .modal-header a:link,
    .modal-header a:visited,
    .modal-header a:hover,
    .modal-header a:active {
        text-decoration: none;
        color: white;
    }
</style>

@stop

@section('body')
<div class="container">

  {!! Former::open('/client/password/reset')->addClass('form-signin')->rules(array(
        'password' => 'required',
        'password_confirmation' => 'required',        
  )) !!}

    <div class="modal-header">
        @if (!isset($account) || !$account->hasFeature(FEATURE_WHITE_LABEL))
            <a href="{{ NINJA_WEB_URL }}" target="_blank">
                <img src="{{ asset('images/icon-login.png') }}" /> 
                <h4>Invoice Ninja | {{ trans('texts.set_password') }}</h4>
            </a>
        @else
            <h4>{{ trans('texts.set_password') }}</h4>
        @endif
    </div>
    <div class="inner">

      <input type="hidden" name="token" value="{{{ $token }}}">
      <input type="hidden" name="contact_key" value="{{{ $contact_key }}}">

      <p>
        {!! Former::password('password')->placeholder(trans('texts.password'))->raw() !!}               
        {!! Former::password('password_confirmation')->placeholder(trans('texts.confirm_password'))->raw() !!}

    </p>

    <p>{!! Button::success(trans('texts.save'))->large()->submit()->block() !!}</p>


    @if (count($errors->all()))
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </div>
    @endif

    <!-- if there are login errors, show them here -->
    @if (Session::has('warning'))
    <div class="alert alert-warning">{{ Session::get('warning') }}</div>
    @endif

    @if (Session::has('message'))
    <div class="alert alert-info">{{ Session::get('message') }}</div>
    @endif

    @if (Session::has('error'))
    <div class="alert alert-danger">{{ Session::get('error') }}</div>
    @endif


    {!! Former::close() !!}
</div>

</div>

@stop