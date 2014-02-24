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

	{{ Former::populate($account) }}
	{{ Former::populateField('first_name', $account->users()->first()->first_name) }}
	{{ Former::populateField('last_name', $account->users()->first()->last_name) }}
	{{ Former::populateField('email', $account->users()->first()->email) }}	
	{{ Former::populateField('phone', $account->users()->first()->phone) }}

	<div class="row">
		<div class="col-md-5">

			{{ Former::legend('Details') }}
			{{ Former::text('name') }}
			{{ Former::file('logo')->max(2, 'MB')->accept('image')->inlineHelp('Supported: JPEG, GIF and PNG. Recommnded size: 120px width, 80px height') }}

			@if (file_exists($account->getLogoPath()))
				<center>
					{{ HTML::image($account->getLogoPath(), "Logo") }}
				</center><br/>
			@endif

			{{ Former::select('size_id')->addOption('','')->label('Size')
				->fromQuery($sizes, 'name', 'id') }}
			{{ Former::select('industry_id')->addOption('','')->label('Industry')
				->fromQuery($industries, 'name', 'id') }}

			{{ Former::legend('Address') }}	
			{{ Former::text('address1')->label('Street') }}
			{{ Former::text('address2')->label('Apt/Suite') }}
			{{ Former::text('city') }}
			{{ Former::text('state')->label('State/Province') }}
			{{ Former::text('postal_code') }}
			{{ Former::select('country_id')->addOption('','')->label('Country')
				->fromQuery($countries, 'name', 'id') }}

		</div>
	
		<div class="col-md-5 col-md-offset-1">		

			{{ Former::legend('Users') }}
			{{ Former::text('first_name') }}
			{{ Former::text('last_name') }}
			{{ Former::text('email') }}
			{{ Former::text('phone') }}


			{{ Former::legend('Localization') }}
			{{ Former::select('currency_id')->addOption('','')->label('Currency')
				->fromQuery($currencies, 'name', 'id') }}			
			{{ Former::select('timezone_id')->addOption('','')->label('Timezone')
				->fromQuery($timezones, 'location', 'id') }}
			{{ Former::select('date_format_id')->addOption('','')->label('Date Format')
				->fromQuery($dateFormats, 'label', 'id') }}
			{{ Former::select('datetime_format_id')->addOption('','')->label('Date/Time Format')
				->fromQuery($datetimeFormats, 'label', 'id') }}


		</div>
	</div>
	
	<center>
		{{ Button::lg_primary_submit('Save')->append_with_icon('floppy-disk') }}
	</center>

	{{ Former::close() }}

	<script type="text/javascript">

		$(function() {
			$('#country_id').combobox();
		});
		
	</script>

@stop