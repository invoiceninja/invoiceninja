@extends('header')

@section('content')

	{!! Former::open($url)
            ->addClass('col-md-10 col-md-offset-1 warn-on-exit')
            ->method($method)
            ->rules([
                'name' => 'required',
				'client_id' => 'required',
            ]) !!}

    @if ($project)
        {!! Former::populate($project) !!}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
    </span>

	<div class="row">
        <div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.project') !!}</h3>
            </div>
            <div class="panel-body">

				@if ($project)
					{!! Former::plaintext('client_name')
							->value($project->client->getDisplayName()) !!}
				@else
					{!! Former::select('client_id')
							->addOption('', '')
							->label(trans('texts.client'))
							->addGroupClass('client-select') !!}
				@endif

                {!! Former::text('name') !!}


            </div>
            </div>

        </div>
    </div>


	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(url('/expense_categories'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}

    <script>

		var clients = {!! $clients !!};

        $(function() {
			var $clientSelect = $('select#client_id');
            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                var clientName = getClientDisplayName(client);
                if (!clientName) {
                    continue;
                }
                $clientSelect.append(new Option(clientName, client.public_id));
            }
			@if ($clientPublicId)
				$clientSelect.val({{ $clientPublicId }});
			@endif
			$clientSelect.combobox().focus();

			$('.client-select input.form-control').focus();
        });

    </script>

@stop
