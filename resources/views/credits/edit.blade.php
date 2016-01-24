@extends('header')

@section('content')

	
	{!! Former::open($url)->addClass('col-md-10 col-md-offset-1 warn-on-exit')->method($method)->rules(array(
			'client' => 'required',
  		'amount' => 'required',		
	)) !!}
	
	<div class="row">
        <div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

			{!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
			{!! Former::text('amount') !!}
			{!! Former::text('credit_date')
                        ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                        ->addGroupClass('credit_date')
                        ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
			{!! Former::textarea('private_notes') !!}

            </div>
            </div>

        </div>
    </div>


	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/credits'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}

	<script type="text/javascript">

	
	var clients = {!! $clients !!};

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
		$('#credit_date').datepicker('update', new Date());

        @if (!$clientPublicId)
            $('.client-select input.form-control').focus();
        @else
            $('#amount').focus();
        @endif

        $('.credit_date .input-group-addon').click(function() {
            toggleDatePicker('credit_date');
        });
	});

	</script>

@stop