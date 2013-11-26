@extends('accounts.nav')

@section('content')	
	@parent	

	<style type="text/css">

	#logo {
		padding-top: 6px;
	}

	</style>

	{{ Former::open_for_files()->addClass('col-md-9 col-md-offset-1')->rules(array(
  		'name' => 'required',
  		'email' => 'email|required'
	)); }}

	{{ Former::populate($account); }}
	{{ Former::populateField('first_name', $account->users()->first()->first_name) }}
	{{ Former::populateField('last_name', $account->users()->first()->last_name) }}
	@if (!$account->users()->first()->is_guest)
		{{ Former::populateField('email', $account->users()->first()->email) }}
	@endif
	{{ Former::populateField('phone', $account->users()->first()->phone) }}

	{{ Former::legend('Account') }}
	{{ Former::text('name') }}

	{{ Former::file('logo')->max(2, 'MB')->accept('image')->wrap('test') }}

	@if (file_exists($account->getLogoPath()))
		<center>
			{{ HTML::image($account->getLogoPath(), "Logo") }}
		</center>
	@endif

	{{ Former::legend('Users') }}
	{{ Former::text('first_name') }}
	{{ Former::text('last_name') }}
	{{ Former::text('email')->label('Email/Username') }}
	{{ Former::text('phone') }}

	
	{{ Former::legend('Address') }}	
	{{ Former::text('address1')->label('Street') }}
	{{ Former::text('address2')->label('Apt/Floor') }}
	{{ Former::text('city') }}
	{{ Former::text('state') }}
	{{ Former::text('postal_code') }}

	{{ Former::actions( Button::lg_primary_submit('Save') ) }}
	{{ Former::close() }}


@stop