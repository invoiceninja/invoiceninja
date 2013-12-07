<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="shortcut icon" href="../../docs-assets/ico/favicon.png">

    <title></title>

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->

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

    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.js" type="text/javascript"></script>
    
    {{ Basset::show('bootstrapper.css') }}
	{{ Basset::show('bootstrapper.js') }}

  </head>

  <body>

    <div class="container">

		{{ Form::open(array('url' => 'login', 'class' => 'form-signin')) }}
			<h2 class="form-signin-heading">Please sign in</h2>

			<p>
				{{ $errors->first('email') }}
				{{ $errors->first('password') }}
			</p>

			<p>
				{{ Form::text('email', Input::old('email'), array('placeholder' => 'Email address')) }}
				{{ Form::password('password', array('placeholder' => 'Password')) }}
			</p>

			<p>{{ Button::primary_submit('Sign In', array('class' => 'btn-lg'))->block() }}</p>

			{{ link_to('user/forgot_password', 'Recover your password') }}

			<!-- if there are login errors, show them here -->
			@if ( Session::get('error') )
            	<div class="alert alert-error">{{{ Session::get('error') }}}</div>
        	@endif

	        @if ( Session::get('notice') )
    	        <div class="alert">{{{ Session::get('notice') }}}</div>
	        @endif


		{{ Form::close() }}

    </div>

  </body>
</html>



