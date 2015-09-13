@extends('accounts.nav')

@section('content')
	@parent

	{!! Former::open('company/import_export')->addClass('col-md-8 col-md-offset-2 warn-on-exit') !!}

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.import_clients') !!}</h3>
      </div>
        <div class="panel-body">

	@if ($headers)

		<label for="header_checkbox">
			<input type="checkbox" name="header_checkbox" id="header_checkbox" {{ $hasHeaders ? 'CHECKED' : '' }}> {{ trans('texts.first_row_headers') }}
		</label>

        <p>&nbsp;</p>

		<table class="table invoice-table">
			<thead>
				<tr>
					<th>{{ trans('texts.column') }}</th>
					<th class="col_sample">{{ trans('texts.sample') }}</th>
					<th>{{ trans('texts.import_to') }}</th>
				</tr>	
			</thead>		
		@for ($i=0; $i<count($headers); $i++)
			<tr>
				<td>{{ $headers[$i] }}</td>
				<td class="col_sample">{{ $data[1][$i] }}</td>
				<td>{!! Former::select('map[' . $i . ']')->options($columns, $mapped[$i], true)->raw() !!}</td>
			</tr>
		@endfor
		</table>

        <p>&nbsp;</p>

		<span id="numClients"></span>
	@endif

    </div>
    </div>


	{!! Former::actions( 
            Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/company/import_export'))->appendIcon(Icon::create('remove-circle')),
            Button::success(trans('texts.import'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))) !!}
	{!! Former::close() !!}

	<script type="text/javascript">

		$(function() {

			var numClients = {{ count($data) }};
			function setSampleShown() {
				if ($('#header_checkbox').is(':checked')) {
					$('.col_sample').show();
					setNumClients(numClients - 1);
				} else {
					$('.col_sample').hide();
					setNumClients(numClients);
				}				
			}

			function setNumClients(num)
			{
				if (num == 1)
				{
					$('#numClients').html("1 {{ trans('texts.client_will_create') }}");
				}
				else
				{
					$('#numClients').html(num + " {{ trans('texts.clients_will_create') }}");
				}
			}

			$('#header_checkbox').click(setSampleShown);
			setSampleShown();

		});

	</script>

@stop