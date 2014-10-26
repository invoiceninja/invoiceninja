@extends('master')

@section('head')	

  <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/> 
  <link href="{{ asset('css/style.css') }}" rel="stylesheet" type="text/css"/>    

  <style type="text/css">
		body {
		  padding-top: 40px;
		  padding-bottom: 40px;
		}
       .modal-header {
          border-top-left-radius: 3px;
          border-top-right-radius: 3px;
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
		
  </style>

@stop

@section('body')
    <div class="container">

		{{ Former::open('login')->addClass('form-signin') }}
			<div class="modal-header">
                <img src="{{ asset('images/icon-login.png') }}" />
                <h4>Invoice Ninja Account Login</h4></div>
            <div class="inner">
			<p>
				{{ $errors->first('login_email') }}
				{{ $errors->first('login_password') }}
			</p>

			<p>
				{{ Form::text('login_email', Input::old('login_email'), array('placeholder' => 'Email address')) }}
				{{ Form::password('login_password', array('placeholder' => 'Password')) }}
			</p>

			<p>{{ Button::success_submit('Let’s go', array('class' => 'btn-lg'))->block() }}</p>
            <p class="link">
			{{ link_to('forgot_password', 'Forgot your password?') }}
            </p>
		
			<!-- if there are login errors, show them here -->
			@if ( Session::get('error') )
            	<div class="alert alert-error">{{{ Session::get('error') }}}</div>
        	@endif

	        @if ( Session::get('notice') )
    	        <div class="alert">{{{ Session::get('notice') }}}</div>
	        @endif
            </div>

		{{ Former::close() }}

    </div>

@stop