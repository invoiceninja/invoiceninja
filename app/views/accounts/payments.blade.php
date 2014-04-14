@extends('accounts.nav')

@section('content')	
	@parent	

	{{ Former::open()->addClass('col-md-8 col-md-offset-2') }}	
	{{ Former::populate($account) }}

	{{ Former::legend('Payment Gateway') }}
		
	@if ($accountGateway)
		{{ Former::populateField('gateway_id', $accountGateway->gateway_id) }}
		{{ Former::populateField('recommendedGateway_id', $accountGateway->gateway_id) }}
		@foreach ($accountGateway->fields as $field => $junk)
			@if (in_array($field, ['solutionType', 'landingPage', 'headerImageUrl', 'brandName']))
				{{-- do nothing --}}
			@else
				{{ Former::populateField($accountGateway->gateway_id.'_'.$field, $config->$field) }}
			@endif
		@endforeach
	@endif
	
	<div class="two-column">
	{{ Former::radios('recommendedGateway_id')->label('Recommended Gateways')
			->radios($recommendedGateways)->class('recommended-gateway')
	}}
	</div>

	{{ Former::select('gateway_id')->label('PayPal & Other Gateways')->addOption('', '')
		->dataClass('gateway-dropdown')
		->fromQuery($dropdownGateways, 'name', 'id')
		->onchange('setFieldsShown()'); }}

	@foreach ($gateways as $gateway)

		<div id="gateway_{{ $gateway->id }}_div" class='gateway-fields' style="display: none">
			@foreach ($gateway->fields as $field => $details)

				@if (in_array($field, ['solutionType', 'landingPage', 'headerImageUrl', 'brandName']))
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

	function setFieldsShown() {
		var recommendedVal = $('input:radio[name=recommendedGateway_id]:checked').val();
		var gatewayVal = $('#gateway_id').val();
		var val = recommendedVal && recommendedVal != 1000000 ? recommendedVal : gatewayVal;
		
		$('.gateway-fields').hide();
		$('#gateway_' + val + '_div').show();
		
		$('#gateway_id').parent().parent().hide();
		if(!$('input:radio[name=recommendedGateway_id][value!=1000000]:checked').val())
		{
			$('.recommended-gateway[value=1000000]').attr('checked', true);
			$('#gateway_id').parent().parent().show();
		}
	}

	$(document).ready(function() {
		$('.recommended-gateway').change(
			function(){
				var recVal = $(this).val();
				
				if(recVal == 1000000)
				{
					$('#gateway_id').parent().parent().show();
				}
				else
				{
					$('#gateway_id').parent().parent().hide();
				}
				
				setFieldsShown();
			}
		);
		
		$('.recommended-gateway[other != true]').each(function(){
			var contents = $(this).parent().contents();
			contents[contents.length - 1].nodeValue = '';
			$(this).after('<img src="' +$(this).attr('data-imageUrl') + '" /><br />');
			$(this).parent().children().last().after('<a href="' + $(this).attr('data-siteUrl') + '">Create an account</a>');
		});
		

		setFieldsShown();
		$('.two-column .form-group .col-lg-8').removeClass('col-lg-8');
		$('.two-column .form-group .col-sm-8').removeClass('col-sm-8');
	});

	</script>

@stop