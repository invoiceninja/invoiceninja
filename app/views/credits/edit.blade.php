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
	@endif

	
	<div class="row">
		<div class="col-md-8">

			@if ($credit)
				{{ Former::legend('Edit Credit') }}
			@else
				{{ Former::legend('New Credit') }}
			@endif

			{{ Former::select('client')->fromQuery($clients, 'name', 'public_id')->select($client ? $client->public_id : '')->addOption('', '')->addGroupClass('client-select') }}
			{{ Former::text('amount') }}
			{{ Former::text('credit_date')->data_date_format(DEFAULT_DATE_PICKER_FORMAT) }}

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

	$(function() {

		var $input = $('select#client');		
		$input.combobox();

		$('#credit_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});

	});

	</script>

@stop
