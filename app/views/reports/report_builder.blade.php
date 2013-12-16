@extends('header')

@section('head')
	@parent

	<script src="{{ asset('js/chart.js') }}" type="text/javascript"></script>		
@stop

@section('content')
	
	<p>&nbsp;</p>
	
	<div class="row">
		<div class="col-lg-3">

			{{ Former::open() }}
			{{ Former::populateField('start_date', $startDate) }}
			{{ Former::populateField('end_date', $endDate) }}
			{{ Former::select('chart_type')->options($chartTypes, $chartType) }}
			{{ Former::select('group_by')->options($dateTypes, $groupBy) }}
			{{ Former::text('start_date') }}
			{{ Former::text('end_date') }}
			{{ Former::actions( Button::primary_submit('Generate') ) }}
			{{ Former::close() }}

		</div>
		<div class="col-lg-9">
			<canvas id="monthly-reports" width="850" height="400"></canvas>
		</div>

	</div>

	<script type="text/javascript">

	var ctx = document.getElementById('monthly-reports').getContext('2d');
	var chart = {
		labels: {{ json_encode($labels) }},		
		datasets: [
		@foreach ($datasets as $dataset)
			{
				data: {{ json_encode($dataset['totals']) }},
				fillColor : "rgba({{ $dataset['colors'] }},0.5)",
				strokeColor : "rgba({{ $dataset['colors'] }},1)",
			},
		@endforeach
		]
	}

	var options = {		
		scaleOverride: true,
		scaleSteps: 10,
		scaleStepWidth: {{ $scaleStepWidth }},
		scaleStartValue: 0,
		scaleLabel : "<%=formatMoney(value)%>",
	};

	new Chart(ctx).{{ $chartType }}(chart, options);

	</script>

@stop


@section('onReady')

	$('#start_date, #end_date').datepicker({
		autoclose: true,
		todayHighlight: true
	});

@stop