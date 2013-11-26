@extends('header')


@section('onReady')
	$('input#name').focus();
@stop


@section('content')

	<!--<h3>{{ $title }} Client</h3>-->
	
	@if ($client)
		{{ Former::populate($client); }}
	@endif

	{{ Former::open($url)->addClass('col-md-9 col-md-offset-1')->method($method)->rules(array(
  		'name' => 'required',
  		'email' => 'email'  		
	)); }}

	
	{{ Former::legend('Organization') }}
	{{ Former::text('name') }}
	{{ Former::text('work_phone')->label('Phone') }}
	{{ Former::textarea('notes') }}

	{{ Former::legend('Contacts') }}
	{{ Former::text('first_name') }}
	{{ Former::text('last_name') }}
	{{ Former::text('email') }}
	{{ Former::text('phone') }}	

	{{ Former::legend('Address') }}
	{{ Former::text('address1')->label('Street') }}
	{{ Former::text('address2')->label('Apt/Floor') }}
	{{ Former::text('city') }}
	{{ Former::text('state') }}
	{{ Former::text('postal_code') }}

	{{ Former::actions()->lg_primary_submit('Save') }}
	{{ Former::close() }}

@stop