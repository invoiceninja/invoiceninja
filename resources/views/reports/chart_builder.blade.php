@extends('accounts.nav')

@section('head')
	@parent

	<script src="{!! asset('js/Chart.js') !!}" type="text/javascript"></script>		
@stop

@section('content')
	@parent
	@include('accounts.nav_advanced')


   {!! Button::primary(trans('texts.data_visualizations'))
            ->asLinkTo(URL::to('/company/advanced_settings/data_visualizations'))
            ->withAttributes(['class' => 'pull-right'])
            ->appendIcon(Icon::create('globe')) !!}

    <p>&nbsp;</p>
    <p>&nbsp;</p>

	<div class="row">
		<div class="col-lg-4">
            <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.settings') !!}</h3>
            </div>            
            <div class="panel-body">    


			{!! Former::open()->rules(['start_date' => 'required', 'end_date' => 'required'])->addClass('warn-on-exit') !!}            

            <div style="display:none">
            {!! Former::text('action') !!}
            </div>

			{!! Former::populateField('start_date', $startDate) !!}
			{!! Former::populateField('end_date', $endDate) !!}
            {!! Former::populateField('enable_report', intval($enableReport)) !!}
            {!! Former::populateField('enable_chart', intval($enableChart)) !!}

			{!! Former::text('start_date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
                    ->addGroupClass('start_date')
					->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'start_date\')"></i>') !!}
			{!! Former::text('end_date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
                    ->addGroupClass('end_date')
					->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'end_date\')"></i>') !!}

            <p>&nbsp;</p>
            {!! Former::checkbox('enable_report')->text(trans('texts.enable')) !!}                
            {!! Former::select('report_type')->options($reportTypes, $reportType)->label(trans('texts.group_by')) !!}
            
            <p>&nbsp;</p>
            {!! Former::checkbox('enable_chart')->text(trans('texts.enable')) !!}                
            {!! Former::select('group_by')->options($dateTypes, $groupBy) !!}
            {!! Former::select('chart_type')->options($chartTypes, $chartType) !!}

            <p>&nbsp;</p>
			@if (Auth::user()->isPro())
				{!! Former::actions( 
                        Button::primary(trans('texts.export'))->withAttributes(array('onclick' => 'onExportClick()'))->appendIcon(Icon::create('export')),
                        Button::success(trans('texts.run'))->withAttributes(array('id' => 'submitButton'))->submit()->appendIcon(Icon::create('play'))
                    ) !!}
			@else
			<script>
			    $(function() {   
			    	$('form.warn-on-exit').find('input, select').prop('disabled', true);
			    });
			</script>	
			@endif
			
			{!! Former::close() !!}

        </div>
        </div>

		</div>
		<div class="col-lg-8">

            @if ($enableReport)
            <div class="panel panel-default">
            <div class="panel-body">
            <table class="table table-striped invoice-table">
            <thead>                
                <tr>
                    @foreach ($columns as $column)    
                        <th>
                            {{ trans("texts.{$column}") }}
                        </th>
                    @endforeach
                </tr>                
            </thead>
            <tbody>
                @foreach ($displayData as $record)
                    <tr>
                        @foreach ($record as $field)
                            <td>
                                {!! $field !!}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><b>{{ trans('texts.totals') }}</b></td>
                    @if (!$reportType)
                        <td></td>
                        <td></td>
                    @endif
                    <td>
                        @foreach ($reportTotals['amount'] as $currencyId => $total)
                            <b>{{ Utils::formatMoney($total, $currencyId) }}</b><br/>
                        @endforeach
                    </td>
                    <td>
                        @foreach ($reportTotals['paid'] as $currencyId => $total)
                            <b>{{ Utils::formatMoney($total, $currencyId) }}</b><br/>
                        @endforeach
                    </td>
                    <td>
                        @foreach ($reportTotals['balance'] as $currencyId => $total)
                            <b>{{ Utils::formatMoney($total, $currencyId) }}</b><br/>
                        @endforeach
                    </td>
                </tr>                
            </tfoot>
            </table>

            </div>
            </div>       
            @endif

            @if ($enableChart)
            <div class="panel panel-default">
            <div class="panel-body">
    			<canvas id="monthly-reports" width="700" height="400"></canvas>
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
            </div>
            @endif

		</div>

	</div>

	<script type="text/javascript">

    function onExportClick() {
        $('#action').val('export');
        $('#submitButton').click();
        $('#action').val('');    
    }

	var ctx = document.getElementById('monthly-reports').getContext('2d');
	var chart = {
		labels: {!! json_encode($labels) !!},
		datasets: [
		@foreach ($datasets as $dataset)
			{
				data: {!! json_encode($dataset['totals']) !!},
				fillColor : "rgba({!! $dataset['colors'] !!},0.5)",
				strokeColor : "rgba({!! $dataset['colors'] !!},1)",
			},
		@endforeach
		]
	}

	var options = {		
		scaleOverride: true,
		scaleSteps: 10,
		scaleStepWidth: {!! $scaleStepWidth !!},
		scaleStartValue: 0,
		scaleLabel : "<%=value%>",
	};

    $(function() {
        $('.start_date .input-group-addon').click(function() {
            toggleDatePicker('start_date');
        });
        $('.end_date .input-group-addon').click(function() {
            toggleDatePicker('end_date');
        });
    })

	new Chart(ctx).{!! $chartType !!}(chart, options);

	</script>

@stop


@section('onReady')

	$('#start_date, #end_date').datepicker({
		autoclose: true,
		todayHighlight: true,
		keyboardNavigation: false
	});

@stop