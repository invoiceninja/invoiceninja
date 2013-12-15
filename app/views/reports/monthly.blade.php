@extends('header')

@section('head')
	@parent

	<script src="{{ asset('js/chart.js') }}" type="text/javascript"></script>		
@stop

@section('content')

	<center style="padding-top: 40px">
		<canvas id="monthly-reports" width="800" height="300"></canvas>
	</center>

	<script type="text/javascript">

	var ctx = document.getElementById('monthly-reports').getContext('2d');
	var chart = {
		labels: {{ json_encode($dates)}},		
		datasets: [{
			data: {{ json_encode($totals) }},
			fillColor : "rgba(151,187,205,0.5)",
			strokeColor : "rgba(151,187,205,1)",
		}]
	}

	var options = {		
		scaleOverride: true,
		scaleSteps: 10,
		scaleStepWidth: {{ $scaleStepWidth }},
		scaleStartValue: 0,
		scaleLabel : "<%=formatMoney(value)%>",
	};

	new Chart(ctx).Bar(chart, options);

	</script>

@stop