@extends('accounts.nav')

@section('content')	
	@parent	

	{{ Former::open()->addClass('col-md-9 col-md-offset-1') }}	
	{{ Former::legend('Payment Gateways') }}

	@foreach ($gateways as $gateway)

		{{ Former::checkbox('gateway_'.$gateway->id)->label('')->text($gateway->provider)
			->check($account->isGatewayConfigured($gateway->id)) }}

		<div id="gateway_{{ $gateway->id }}_div" style="display: none">
			@foreach ($gateway->fields as $field => $details)
				@if ($config = $account->getGatewayConfig($gateway->id))
				{{ 	Former::populateField($gateway->id.'_'.$field, $config[$field]) }}
				@endif
				@if (in_array($field, array('username','password','signature')))		
					{{ Former::text($gateway->id.'_'.$field)->label($field) }}
				@endif
			@endforeach
		</div>

	@endforeach

	{{ Former::actions( Button::lg_primary_submit('Save') ) }}
	{{ Former::close() }}


	<script type="text/javascript">

	$(function() {

		function setFieldsShown(id) {
			if ($('#gateway_' + id).is(':checked')) {
				$('#gateway_' + id + '_div').show();
			} else {
				$('#gateway_' + id + '_div').hide();
			}				
		}

		@foreach ($gateways as $gateway)
			$('#gateway_{{ $gateway->id }}').click(function() { 				
				setFieldsShown('{{ $gateway->id }}');
			});			
			setFieldsShown('{{ $gateway->id }}');
		@endforeach
	});

	</script>

@stop