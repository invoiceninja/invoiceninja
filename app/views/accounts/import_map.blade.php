@extends('accounts.nav')

@section('content')
	@parent

	{{ Former::open('company/import_export') }}
	{{ Former::legend('import_clients') }}

	@if ($headers)

		<label for="header_checkbox">
			<input type="checkbox" name="header_checkbox" id="header_checkbox" {{ $hasHeaders ? 'CHECKED' : '' }}> {{ trans('texts.first_row_headers') }}
		</label>

		<table class="table">
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
				<td>{{ Former::select('map[' . $i . ']')->options($columns, $mapped[$i], true)->raw() }}</td>
			</tr>
		@endfor
		</table>

		<span id="numClients"></span>
	@endif


	{{ Former::actions( Button::lg_primary_submit(trans('texts.import')), '&nbsp;|&nbsp;', link_to('company/import', trans('texts.cancel')) ) }}
	{{ Former::close() }}

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