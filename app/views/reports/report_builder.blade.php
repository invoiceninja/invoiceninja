@extends('accounts.nav')

@section('head')
	@parent

	<script src="{{ asset('js/Chart.js') }}" type="text/javascript"></script>		
@stop

@section('content')
	@parent
	@include('accounts.nav_advanced')

  {{ Former::open() }}
  {{ Former::legend('chart_builder') }}
  {{ Former::close() }}

	<div class="row">
		<div class="col-lg-4">

			{{ Former::open()->addClass('warn-on-exit') }}
			{{ Former::populateField('start_date', $startDate) }}
			{{ Former::populateField('end_date', $endDate) }}
			{{ Former::select('chart_type')->options($chartTypes, $chartType) }}
			{{ Former::select('group_by')->options($dateTypes, $groupBy) }}
			{{ Former::text('start_date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
					->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'start_date\')"></i>') }}
			{{ Former::text('end_date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
					->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'end_date\')"></i>') }}

			@if (Auth::user()->isPro())
				{{ Former::actions( Button::primary_submit('Generate') ) }}
			@else
			<script>
			    $(function() {   
			    	$('form.warn-on-exit').find('input, select').prop('disabled', true);
			    });
			</script>	
			@endif
			
			{{ Former::close() }}

			<p>&nbsp;</p>
			<div style="padding-bottom:8px">
				<div style="float:left; height:22px; width:60px; background-color:rgba(78,205,196,.5); border: 1px solid rgba(78,205,196,1)"></div>
				<div style="vertical-align: middle">&nbsp;Invoices</div>
			</div>			
			<div style="padding-bottom:8px; clear:both">
				<div style="float:left; height:22px; width:60px; background-color:rgba(255,107,107,.5); border: 1px solid rgba(255,107,107,1)"></div>
				<div style="vertical-align: middle">&nbsp;Payments</div>
			</div>
			<div style="clear:both">
				<div style="float:left; height:22px; width:60px; background-color:rgba(199,244,100,.5); border: 1px solid rgba(199,244,100,1)"></div>
				<div style="vertical-align: middle">&nbsp;Credits</div>
			</div>

		</div>
		<div class="col-lg-8">
			<canvas id="monthly-reports" width="772" height="400"></canvas>
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
		todayHighlight: true,
		keyboardNavigation: false
	});

@stop