@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

	
	{{ Former::open($url)->addClass('col-md-10 col-md-offset-1 main_form')->method($method)->rules(array(
		'client' => 'required',
  		'amount' => 'required',		
	)); }}
	
	<div class="row">
		<div class="col-md-8">

			{{ Former::select('client')->addOption('', '')->addGroupClass('client-select') }}
			{{ Former::text('amount') }}
			{{ Former::text('credit_date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT)) }}
			{{-- Former::select('currency_id')->addOption('','')->label('Currency')
				->fromQuery($currencies, 'name', 'id')->select(Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY)) --}}
			{{ Former::textarea('private_notes') }}

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

		var $clientSelect = $('select#client');		
		for (var i=0; i<clients.length; i++) {
			var client = clients[i];
			$clientSelect.append(new Option(getClientDisplayName(client), client.public_id));
		}	

		if ({{ $clientPublicId ? 'true' : 'false' }}) {
			$clientSelect.val({{ $clientPublicId }});
		}

		$clientSelect.combobox();
		


		$('#currency_id').combobox();
		
		$('#credit_date').datepicker('update', new Date({{ strtotime(Utils::today()) * 1000 }}));

	});

	</script>

@stop
