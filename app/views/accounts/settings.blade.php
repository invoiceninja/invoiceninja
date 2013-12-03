@extends('accounts.nav')

@section('content')	
	@parent	

	{{ Former::open()->addClass('col-md-10 col-md-offset-1') }}	
	{{ Former::legend('Payment Gateway') }}

	@if ($accountGateway)
		{{ Former::populateField('gateway_id', $accountGateway->gateway_id) }}
		@foreach ($accountGateway->fields as $field => $junk)
			{{ Former::populateField($accountGateway->gateway_id.'_'.$field, $config->$field) }}
		@endforeach
	@endif

	{{ Former::select('gateway_id')->label('Provider')->addOption('', '')->fromQuery($gateways, 'name', 'id')->onchange('setFieldsShown()'); }}

	@foreach ($gateways as $gateway)

		<div id="gateway_{{ $gateway->id }}_div" style="display: none">
			@foreach ($gateway->fields as $field => $details)

				@if ($field == 'solutionType' || $field == 'landingPage')
					{{-- do nothing --}}
				@elseif ($field == 'testMode' || $field == 'developerMode') 
					{{ Former::checkbox($gateway->id.'_'.$field)->label(toSpaceCase($field))->text('Enable') }}				
				@else
					{{ Former::text($gateway->id.'_'.$field)->label(toSpaceCase($field)) }}				
				@endif

			@endforeach
		</div>
		
	@endforeach


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