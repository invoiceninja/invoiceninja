@extends('accounts.nav')

@section('content')	
	@parent	

	{{ Former::open()->addClass('col-md-8 col-md-offset-2') }}	
	{{ Former::populate($account) }}
	{{ Former::populateField('notify_sent', Auth::user()->notify_sent) }}
	{{ Former::populateField('notify_viewed', Auth::user()->notify_viewed) }}
	{{ Former::populateField('notify_paid', Auth::user()->notify_paid) }}

	{{ Former::legend('Payment Gateway') }}

	@if ($accountGateway)
		{{ Former::populateField('gateway_id', $accountGateway->gateway_id) }}
		@foreach ($accountGateway->fields as $field => $junk)
			@if ($field == 'testMode' || $field == 'developerMode')
				@if ($config->$field)
					{{ Former::populateField($accountGateway->gateway_id.'_'.$field, true ) }}
				@endif
			@else
				{{ Former::populateField($accountGateway->gateway_id.'_'.$field, $config->$field) }}
			@endif
		@endforeach
	@endif

	{{ Former::select('gateway_id')->label('Provider')->addOption('', '')
		->fromQuery($gateways, 'name', 'id')->onchange('setFieldsShown()'); }}

	@foreach ($gateways as $gateway)

		<div id="gateway_{{ $gateway->id }}_div" style="display: none">
			@foreach ($gateway->fields as $field => $details)

				@if ($field == 'solutionType' || $field == 'landingPage')
					{{-- do nothing --}}
				@elseif ($field == 'testMode' || $field == 'developerMode') 
					{{ Former::checkbox($gateway->id.'_'.$field)->label(Utils::toSpaceCase($field))->text('Enable') }}				
				@else
					{{ Former::text($gateway->id.'_'.$field)->label(Utils::toSpaceCase($field)) }}				
				@endif

			@endforeach
		</div>
		
	@endforeach


	{{ Former::legend('Localization') }}
	{{ Former::select('currency_id')->addOption('','')->label('Currency')
		->fromQuery($currencies, 'name', 'id') }}			
	{{ Former::select('timezone_id')->addOption('','')->label('Timezone')
		->fromQuery($timezones, 'location', 'id') }}
	{{ Former::select('date_format_id')->addOption('','')->label('Date Format')
		->fromQuery($dateFormats, 'label', 'id') }}
	{{ Former::select('datetime_format_id')->addOption('','')->label('Date/Time Format')
		->fromQuery($datetimeFormats, 'label', 'id') }}

	{{ Former::legend('Email Notifications') }}
	{{ Former::checkbox('notify_sent')->label('&nbsp;')->text('Email me when an invoice is <b>sent</b>') }}
	{{ Former::checkbox('notify_viewed')->label('&nbsp;')->text('Email me when an invoice is <b>viewed</b>') }}
	{{ Former::checkbox('notify_paid')->label('&nbsp;')->text('Email me when an invoice is <b>paid</b>') }}

	{{ Former::legend('Invoice Terms') }}
	{{ Former::textarea('invoice_terms')->label('Terms') }}

	{{ Former::actions( Button::lg_primary_submit('Save') ) }}
	{{ Former::close() }}


	<script type="text/javascript">

	var gateways = {{ $gateways }};
	function setFieldsShown() {
		var val = $('#gateway_id').val();
		for (var i=0; i<gateways.length; i++) {
			var gateway = gateways[i];			
			if (val == gateway.id) {
				$('#gateway_' + gateway.id + '_div').show();
			} else {
				$('#gateway_' + gateway.id + '_div').hide();	
			}
		}
	}

	$(function() {
		setFieldsShown();
	});

	</script>

@stop