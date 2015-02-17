@extends('accounts.nav')

@section('head') 
    @parent 
    
    <style type="text/css">
    /* bootstrap 3.2.0 fix */
    /* https://github.com/twbs/bootstrap/issues/13984 */
    .radio input[type="radio"],
    .checkbox input[type="checkbox"] {
        margin-left: 0;
        margin-right: 5px;
        height: inherit;
        width: inherit;
        float: left;
        display: inline-block;
        position: relative;
        margin-top: 3px;
    }
    </style>

@stop

@section('content')	
	@parent	

	{{ Former::open()->rule()->addClass('col-md-8 col-md-offset-2 warn-on-exit') }}	
	{{ Former::populate($account) }}

	{{ Former::legend('Payment Gateway') }}
		
	@if ($accountGateway)
		{{ Former::populateField('gateway_id', $accountGateway->gateway_id) }}
		{{ Former::populateField('recommendedGateway_id', $accountGateway->gateway_id) }}
		@if ($config)
			@foreach ($accountGateway->fields as $field => $junk)
				@if (in_array($field, ['solutionType', 'landingPage', 'headerImageUrl', 'brandName']))
					{{-- do nothing --}}
				@elseif (isset($config->$field))
                    {{ Former::populateField($accountGateway->gateway_id.'_'.$field, $config->$field) }}                    
				@endif
			@endforeach
		@endif
	@endif
    
  <div class="two-column">
		{{ Former::checkboxes('creditCardTypes[]')->label('Accepted Credit Cards')
				->checkboxes($creditCardTypes)->class('creditcard-types')
		}}
	</div>

	<p/>&nbsp;<p/>
	
	<div class="two-column">
	{{ Former::radios('recommendedGateway_id')->label('Recommended Gateway')
			->radios($recommendedGateways)->class('recommended-gateway')
	}}
	</div>

	{{ Former::select('gateway_id')->label('Select Gateway')->addOption('', '')
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

			@if ($gateway->getHelp())
				<div class="form-group">
					<label class="control-label col-lg-4 col-sm-4"></label>
					<div class="col-lg-8 col-sm-8 help-block">
						{{ $gateway->getHelp() }}		
					</div>
				</div>					
			@endif

            @if ($gateway->id == GATEWAY_STRIPE)
                {{ Former::select('token_billing_type_id')->options($tokenBillingOptions)->help(trans('texts.token_billing_help')) }}
            @endif
		</div>
		
	@endforeach

	<p/>&nbsp;<p/>

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

	function gatewayLink(url) {
		var host = new URL(url).hostname;
		if (host) {
			openUrl(url, '/affiliate/' + host);
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
			$(this).parent().children().last().after('<a href="#" onclick="gatewayLink(\'' + $(this).attr('data-siteUrl') + '\')" style="padding-left:26px">Create an account</a>');
		});
        
    // TODO: THIS IS JUST TO SHOW THE IMAGES, STYLE IS SET INLINE STYLE
    $('.creditcard-types').each(function(){
			var contents = $(this).parent().contents();
			contents[contents.length - 1].nodeValue = '';
			$(this).after('<img style="width: 60px; display: inline;" src="' +$(this).attr('data-imageUrl') + '" /><br />');
		});
		

		setFieldsShown();
		$('.two-column .form-group .col-lg-8').removeClass('col-lg-8');
		$('.two-column .form-group .col-sm-8').removeClass('col-sm-8');
	});

	</script>

@stop