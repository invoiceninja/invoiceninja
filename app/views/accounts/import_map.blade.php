@extends('accounts.nav')

@section('content')
	@parent

	{{ Former::open('account/import') }}
	{{ Former::legend('Import Clients') }}

	@if ($headers)

		<label for="header_checkbox">
			<input type="checkbox" name="header_checkbox" id="header_checkbox" {{ $hasHeaders ? 'CHECKED' : '' }}> Use first row as headers
		</label>

		<table class="table">
			<thead>
				<tr>
					<th>Column</th>
					<th class="col_sample">Sample</th>
					<th>Import To</th>
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


	{{ Former::actions( Button::lg_primary_submit('Import'), '&nbsp;|&nbsp;', link_to('account/import', 'Cancel') ) }}
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
					$('#numClients').html('1 client will be created');
				}
				else
				{
					$('#numClients').html(num + ' clients will be created');
				}
			}

			$('#header_checkbox').click(setSampleShown);
			setSampleShown();

		});

	</script>

@stop