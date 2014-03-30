@extends('accounts.nav')

@section('content')	
	@parent	

	{{ Former::open()->addClass('col-md-8 col-md-offset-2') }}	
	{{ Former::populate($account) }}
	{{ Former::populateField('notify_sent', intval(Auth::user()->notify_sent)) }}
	{{ Former::populateField('notify_viewed', intval(Auth::user()->notify_viewed)) }}
	{{ Former::populateField('notify_paid', intval(Auth::user()->notify_paid)) }}

	{{ Former::legend('Payment Gateway') }}
	
	{{Former::label('Lorem Ipsum goes here.')}}
	
	<div class="two-column">
	{{ Former::radios('recommendedGateway_id')
		->label('Recommended Gateways')
		->radios($recommendedGateways)
		->class('recommended-gateway')}}
	</div>

	@if ($accountGateway)
		{{ Former::populateField('gateway_id', $accountGateway->gateway_id) }}
		@foreach ($accountGateway->fields as $field => $junk)
			@if (in_array($field, ['solutionType', 'landingPage', 'headerImageUrl', 'brandName']))
				{{-- do nothing --}}
			@else
				{{ Former::populateField($accountGateway->gateway_id.'_'.$field, $config->$field) }}
			@endif
		@endforeach
	@endif

	{{ Former::select('gateway_id')->label('PayPal & Other Gateways')->addOption('', '')
		->dataClass('gateway-dropdown')
		->fromQuery($gateways, 'name', 'id')
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
		var val = $('#gateway_id').val();
		var activeElement = $('.recommended-gateway[value=' + val + ']');
		var recommendedRadios = $('#recommendedGateway_id');
		
		$('.gateway-fields').hide();
		$('#gateway_' + val + '_div').show();
		
		if(activeElement && !activeElement.attr('checked'))
		{
			activeElement.attr('checked', true);
		}
	}

	$(document).ready(function() {
		$('.recommended-gateway').change(
			function(){
				var recVal = $(this).val();
				$('#gateway_id').val(recVal);
				setFieldsShown();
			}
		);
		
		$('select[data-class=gateway-dropdown]').change(function(){
			$('.recommended-gateway').attr('checked', false);
			var activeElement = $('.recommended-gateway[value=' + $(this).val() + ']');
			
			if(activeElement)
			{
				activeElement.attr('checked', true);
			}
		});
		
		$('.recommended-gateway').each(function(){
			var contents = $(this).parent().contents();
			contents[contents.length - 1].nodeValue = '';
			$(this).after('<img src="' +$(this).attr('data-imageUrl') + '" /><br />');
			$(this).parent().children().last().after('<a href="' + $(this).attr('data-siteUrl') + '">Create an account</a>');
			if($(this).attr('data-newRow') && true)
			{
				
			}
		});
		

		setFieldsShown();
		$('.two-column .form-group .col-lg-8').removeClass('col-lg-8');
		$('.two-column .form-group .col-sm-8').removeClass('col-sm-8');
	});

	</script>

@stop