@extends('accounts.nav')

@section('content')	
	@parent
	
	<style type="text/css">

	#logo {
		padding-top: 6px;
	}

	</style>

	{{ Former::open_for_files()->addClass('col-md-10 col-md-offset-1 warn-on-exit')->rules(array(
  		'name' => 'required',
  		'email' => 'email|required'
	)) }}

	{{ Former::populate($account) }}
	{{ Former::populateField('first_name', $account->users()->first()->first_name) }}
	{{ Former::populateField('last_name', $account->users()->first()->last_name) }}
	{{ Former::populateField('email', $account->users()->first()->email) }}	
	{{ Former::populateField('phone', $account->users()->first()->phone) }}

	<div class="row">
		<div class="col-md-5">

			{{ Former::legend('details') }}
			{{ Former::text('name') }}
			{{ Former::text('work_email') }}
			{{ Former::text('work_phone') }}
			{{ Former::file('logo')->max(2, 'MB')->accept('image')->inlineHelp(trans('texts.logo_help')) }}

			@if (file_exists($account->getLogoPath()))
				<center>
					{{ HTML::image($account->getLogoPath(), "Logo") }} &nbsp;
					<a href="#" onclick="deleteLogo()">{{ trans('texts.remove_logo') }}</a>
				</center><br/>
			@endif

			{{ Former::select('size_id')->addOption('','')
				->fromQuery($sizes, 'name', 'id') }}
			{{ Former::select('industry_id')->addOption('','')
				->fromQuery($industries, 'name', 'id') }}

			{{ Former::legend('address') }}	
			{{ Former::text('address1') }}
			{{ Former::text('address2') }}
			{{ Former::text('city') }}
			{{ Former::text('state') }}
			{{ Former::text('postal_code') }}
			{{ Former::select('country_id')->addOption('','')
				->fromQuery($countries, 'name', 'id') }}

		</div>
	
		<div class="col-md-5 col-md-offset-1">		

			{{ Former::legend('users') }}
			{{ Former::text('first_name') }}
			{{ Former::text('last_name') }}
			{{ Former::text('email') }}
			{{ Former::text('phone') }}

			{{ Former::legend('localization') }}
			{{ Former::select('language_id')->addOption('','')
				->fromQuery($languages, 'name', 'id') }}			
			{{ Former::select('currency_id')->addOption('','')
				->fromQuery($currencies, 'name', 'id') }}			
			{{ Former::select('timezone_id')->addOption('','')
				->fromQuery($timezones, 'location', 'id') }}
			{{ Former::select('date_format_id')->addOption('','')
				->fromQuery($dateFormats, 'label', 'id') }}
			{{ Former::select('datetime_format_id')->addOption('','')
				->fromQuery($datetimeFormats, 'label', 'id') }}


		</div>
	</div>
	
	<center>
		{{ Button::lg_success_submit(trans('texts.save'))->append_with_icon('floppy-disk') }}
	</center>

	{{ Former::close() }}

	{{ Form::open(['url' => 'remove_logo', 'class' => 'removeLogoForm']) }}	
	{{ Form::close() }}


	<script type="text/javascript">

		$(function() {
			$('#country_id').combobox();
		});
		
		function deleteLogo() {
			if (confirm("{{ trans('texts.are_you_sure') }}")) {
				$('.removeLogoForm').submit();
			}
		}

	</script>

@stop