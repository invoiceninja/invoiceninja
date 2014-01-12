@extends('master')

@section('head')	

    <style type="text/css">
		body {
		  padding-top: 40px;
		  padding-bottom: 40px;
		  background-color: #eee !important;
		}

		.form-signin {
		  max-width: 330px;
		  padding: 15px;
		  margin: 0 auto;
		}
		.form-signin .form-signin-heading,
		.form-signin .checkbox {
		  margin-bottom: 10px;
		}
		.form-signin .checkbox {
		  font-weight: normal;
		}
		.form-signin .form-control {
		  position: relative;
		  font-size: 16px;
		  height: auto;
		  padding: 10px;
		  -webkit-box-sizing: border-box;
		     -moz-box-sizing: border-box;
		          box-sizing: border-box;
		}
		.form-signin .form-control:focus {
		  z-index: 2;
		}
		.form-signin input[type="text"] {
		  margin-bottom: -1px;
		  border-bottom-left-radius: 0;
		  border-bottom-right-radius: 0;
		}
		.form-signin input[type="password"] {
		  margin-bottom: 10px;
		  border-top-left-radius: 0;
		  border-top-right-radius: 0;
		}
    </style>

@stop

@section('body')
    <div class="container">

		{{ Former::open('login')->addClass('form-signin') }}
			<h2 class="form-signin-heading">Please sign in</h2>

			<p>
				{{ $errors->first('login_email') }}
				{{ $errors->first('login_password') }}
			</p>

			<p>
				{{ Form::text('login_email', Input::old('login_email'), array('placeholder' => 'Email address')) }}
				{{ Form::password('login_password', array('placeholder' => 'Password')) }}
			</p>

			<p>{{ Button::primary_submit('Sign In', array('class' => 'btn-lg'))->block() }}</p>

			{{ link_to('forgot_password', 'Recover your password') }}
		
			<!-- if there are login errors, show them here -->
			@if ( Session::get('error') )
            	<div class="alert alert-error">{{{ Session::get('error') }}}</div>
        	@endif

	        @if ( Session::get('notice') )
    	        <div class="alert">{{{ Session::get('notice') }}}</div>
	        @endif


		{{ Former::close() }}

    </div>

@stop