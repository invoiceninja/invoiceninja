@extends('master')

@section('head')	

	  <link href="{{ asset('vendor/bootstrap/dist/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/> 

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

		{{ Former::open('forgot_password')->addClass('form-signin') }}
			<h2 class="form-signin-heading">Password Recovery</h2>

			<p>
				{{ Form::text('email', Input::old('email'), array('placeholder' => 'Email address')) }}
			</p>

			<p>{{ Button::primary_submit('Send email', array('class' => 'btn-lg'))->block() }}</p>
		
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