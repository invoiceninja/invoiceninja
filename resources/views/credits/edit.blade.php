@extends('header')

@section('content')


	{!! Former::open($url)->addClass('col-md-10 col-md-offset-1 warn-on-exit')->method($method)->rules(array(
			'client_id' => 'required',
  		'amount' => 'required',
	)) !!}

	@if ($credit)
      {!! Former::populate($credit) !!}
      <div style="display:none">
          {!! Former::text('public_id') !!}
      </div>
	@endif

	<div class="row">
        <div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

			@if ($credit)
				{!! Former::plaintext()->label('client')->value($client->present()->link) !!}
			@else
				{!! Former::select('client_id')
						->label('client')
						->addOption('', '')
						->addGroupClass('client-select') !!}
			@endif

			{!! Former::text('amount') !!}

			@if ($credit)
				{!! Former::text('balance') !!}
			@endif

			{!! Former::text('credit_date')
                        ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                        ->addGroupClass('credit_date')
                        ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}

			{!! Former::textarea('public_notes')->rows(4) !!}
			{!! Former::textarea('private_notes')->rows(4) !!}

            </div>
            </div>

        </div>
    </div>


	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/credits'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}

	<script type="text/javascript">


	var clients = {!! $clients ?: 'false' !!};

	$(function() {

		@if ( ! $credit)
			var $clientSelect = $('select#client_id');
			for (var i=0; i<clients.length; i++) {
				var client = clients[i];
	            var clientName = getClientDisplayName(client);
	            if (!clientName) {
	                continue;
	            }
				$clientSelect.append(new Option(clientName, client.public_id));
			}

			if ({{ $clientPublicId ? 'true' : 'false' }}) {
				$clientSelect.val({{ $clientPublicId }});
			}

			$clientSelect.combobox();
		@endif

		$('#currency_id').combobox();
		$('#credit_date').datepicker('update', '{{ $credit ? $credit->credit_date : 'new Date()' }}');

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
