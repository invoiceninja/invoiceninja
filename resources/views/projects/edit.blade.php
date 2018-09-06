@extends('header')

@section('content')

	{!! Former::open($url)
            ->addClass('col-lg-10 col-lg-offset-1 warn-on-exit main-form')
			->autocomplete('off')
            ->method($method)
            ->rules([
                'name' => 'required',
				'client_id' => 'required',
            ]) !!}

    @if ($project)
        {!! Former::populate($project) !!}
		{!! Former::populateField('task_rate', floatval($project->task_rate) ? Utils::roundSignificant($project->task_rate) : '') !!}
		{!! Former::populateField('budgeted_hours', floatval($project->budgeted_hours) ? $project->budgeted_hours : '') !!}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
		{!! Former::text('action') !!}
    </span>

	<div class="row">
        <div class="col-lg-10 col-lg-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

				@if ($project)
					{!! Former::plaintext('client_name')
							->value($project->client ? $project->client->present()->link : '') !!}
				@else
					{!! Former::select('client_id')
							->addOption('', '')
							->label(trans('texts.client'))
							->addGroupClass('client-select') !!}
				@endif

                {!! Former::text('name') !!}

				{!! Former::text('due_date')
	                        ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
	                        ->addGroupClass('due_date')
	                        ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}

				{!! Former::text('budgeted_hours') !!}

				{!! Former::text('task_rate')
						->placeholder($project && $project->client->task_rate ? $project->client->present()->taskRate : $account->present()->taskRate)
				 		->help('task_rate_help') !!}

				@include('partials/custom_fields', ['entityType' => ENTITY_PROJECT])

				{!! Former::textarea('private_notes')->rows(4) !!}

            </div>
            </div>

        </div>
    </div>

	@if(Auth::user()->canCreateOrEdit(ENTITY_PROJECT))
	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/projects'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>
	@endif

	{!! Former::close() !!}

    <script>

		var clients = {!! $clients !!};
		var clientMap = {};

		function submitAction(action) {
            $('#action').val(action);
            $('.main-form').submit();
        }

		function onDeleteClick() {
            sweetConfirm(function() {
                submitAction('delete');
            });
        }

        $(function() {
			var $clientSelect = $('select#client_id');
            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
								clientMap[client.public_id] = client;
                var clientName = getClientDisplayName(client);
                if (!clientName) {
                    continue;
                }
                $clientSelect.append(new Option(clientName, client.public_id));
            }
			@if ($clientPublicId)
				$clientSelect.val({{ $clientPublicId }});
			@endif

			$clientSelect.combobox({highlighter: comboboxHighlighter}).change(function() {
				var client = clientMap[$('#client_id').val()];
				if (client && parseFloat(client.task_rate)) {
					var rate = client.task_rate;
				} else {
					var rate = {{ $account->present()->taskRate ?: 0 }};
				}
				$('#task_rate').attr('placeholder', roundSignificant(rate, true));
			});

			$('#due_date').datepicker('update', '{{ $project ? Utils::fromSqlDate($project->due_date) : '' }}');

			@if ($clientPublicId)
				$('#name').focus();
			@else
				$('.client-select input.form-control').focus();
			@endif
        });

    </script>

@stop
