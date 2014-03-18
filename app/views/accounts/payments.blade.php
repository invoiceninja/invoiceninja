@extends('accounts.nav')

@section('content')	
	@parent	

	{{ Former::open()->addClass('col-md-8 col-md-offset-2') }}	
	{{ Former::populate($account) }}
	{{ Former::populateField('notify_sent', intval(Auth::user()->notify_sent)) }}
	{{ Former::populateField('notify_viewed', intval(Auth::user()->notify_viewed)) }}
	{{ Former::populateField('notify_paid', intval(Auth::user()->notify_paid)) }}

	{{ Former::legend('Payment Gateway') }}

	@if ($accountGateway)
		{{ Former::populateField('gateway_id', $accountGateway->gateway_id) }}
		@foreach ($accountGateway->fields as $field => $junk)
			@if ($field == 'testMode' || $field == 'developerMode')
				@if ($config->$field)
					{{-- Former::populateField($accountGateway->gateway_id.'_'.$field, true ) --}}
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
					{{-- Former::checkbox($gateway->id.'_'.$field)->label(Utils::toSpaceCase($field))->text('Enable') --}}				
				@elseif ($field == 'username' || $field == 'password') 
					{{ Former::text($gateway->id.'_'.$field)->label('API '. ucfirst(Utils::toSpaceCase($field))) }}				
				@else
					{{ Former::text($gateway->id.'_'.$field)->label(Utils::toSpaceCase($field)) }}				
				@endif

			@endforeach
		</div>
		
	@endforeach

	{{ Former::actions( Button::lg_success_submit('Save')->append_with_icon('floppy-disk') ) }}
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