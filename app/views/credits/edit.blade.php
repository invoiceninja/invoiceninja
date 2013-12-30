@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

	
	{{ Former::open($url)->addClass('col-md-10 col-md-offset-1 main_form')->method($method)->rules(array(
		'client' => 'required',
  		'amount' => 'required',		
	)); }}

	@if ($credit)
		{{ Former::populate($credit) }}
	@else
		{{ Former::populateField('credit_date', date('Y-m-d')) }}
	@endif

	
	<div class="row">
		<div class="col-md-8">

			@if ($credit)
				{{ Former::legend('Edit Credit') }}
			@else
				{{ Former::legend('New Credit') }}
			@endif

			{{ Former::select('client')->addOption('', '')->addGroupClass('client-select') }}
			{{ Former::text('amount') }}
			{{ Former::text('credit_date')->data_date_format(DEFAULT_DATE_PICKER_FORMAT) }}
			{{ Former::select('currency_id')->addOption('','')->label('Currency')
				->fromQuery($currencies, 'name', 'id')->select(Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY)) }}

		</div>
		<div class="col-md-6">

		</div>
	</div>

	<center style="margin-top:16px">
		{{ Button::lg_primary_submit('Save') }} &nbsp;|&nbsp;
		{{ link_to('credits/' . ($credit ? $credit->public_id : ''), 'Cancel') }}	
	</center>

	{{ Former::close() }}

	<script type="text/javascript">

	var clients = {{ $clients }};

	$(function() {

		var $input = $('select#client');		
		for (var i=0; i<clients.length; i++) {
			var client = clients[i];
			$input.append(new Option(getClientDisplayName(client), client.public_id));
		}
		@if ($clientPublicId)
			$('select#client').val({{ $clientPublicId }});
		@endif		
		$input.combobox();

		$('#currency_id').combobox();
		
		$('#credit_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});

	});

	</script>

@stop
