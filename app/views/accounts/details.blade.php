@extends('accounts.nav')

@section('content')	
	@parent
	
	<style type="text/css">

	#logo {
		padding-top: 6px;
	}

	</style>

	{{ Former::open_for_files()->addClass('col-md-10 col-md-offset-1')->rules(array(
  		'name' => 'required',
  		'email' => 'email|required'
	)); }}

	{{ Former::populate($account); }}
	{{ Former::populateField('first_name', $account->users()->first()->first_name) }}
	{{ Former::populateField('last_name', $account->users()->first()->last_name) }}
	{{ Former::populateField('email', $account->users()->first()->email) }}	
	{{ Former::populateField('phone', $account->users()->first()->phone) }}

	<div class="row">
		<div class="col-md-6">

			{{ Former::legend('Account') }}
			{{ Former::text('name') }}
			{{ Former::file('logo')->max(2, 'MB')->accept('image')->wrap('test') }}

			@if (file_exists($account->getLogoPath()))
				<center>
					{{ HTML::image($account->getLogoPath(), "Logo") }}
				</center>
			@endif

			{{ Former::legend('Address') }}	
			{{ Former::text('address1')->label('Street') }}
			{{ Former::text('address2')->label('Apt/Suite') }}
			{{ Former::text('city') }}
			{{ Former::text('state')->label('State/Province') }}
			{{ Former::text('postal_code') }}
			{{ Former::select('country_id')->addOption('','')->label('Country')
				->fromQuery($countries, 'name', 'id')->select($account ? $account->country_id : '') }}

		</div>
	
		<div class="col-md-6">		

			{{ Former::legend('Users') }}
			{{ Former::text('first_name') }}
			{{ Former::text('last_name') }}
			{{ Former::text('email') }}
			{{ Former::text('phone') }}
		</div>
	</div>
	
	<center>
		{{ Button::lg_primary_submit('Save') }}
	</center>

	{{ Former::close() }}

	<script type="text/javascript">

		$(function() {
			$('#country_id').combobox();
		});
		
	</script>

@stop