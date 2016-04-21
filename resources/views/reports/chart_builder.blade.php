@extends('header')

@section('head')
	@parent

	<script src="{!! asset('js/Chart.js') !!}" type="text/javascript"></script>		
@stop

@section('content')
	@parent
	@include('accounts.nav', ['selected' => ACCOUNT_CHARTS_AND_REPORTS, 'advanced' => true])


    {!! Former::open()->rules(['start_date' => 'required', 'end_date' => 'required'])->addClass('warn-on-exit') !!}            

    <div style="display:none">
    {!! Former::text('action') !!}
    </div>

    {!! Former::populateField('start_date', $startDate) !!}
    {!! Former::populateField('end_date', $endDate) !!}
    {!! Former::populateField('enable_report', intval($enableReport)) !!}
    {!! Former::populateField('enable_chart', intval($enableChart)) !!}

	<div class="row">
		<div class="col-lg-12">
            <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.report_settings') !!}</h3>
            </div>            
            <div class="panel-body">    
                <div class="row">
                    <div class="col-md-6">

            			{!! Former::text('start_date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
                                ->addGroupClass('start_date')
            					->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'start_date\')"></i>') !!}
            			{!! Former::text('end_date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
                                ->addGroupClass('end_date')
            					->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'end_date\')"></i>') !!}

                        <p>&nbsp;</p>
                        {!! Former::actions( 
                                Button::primary(trans('texts.export'))->withAttributes(array('onclick' => 'onExportClick()'))->appendIcon(Icon::create('export')),
                                Button::success(trans('texts.run'))->withAttributes(array('id' => 'submitButton'))->submit()->appendIcon(Icon::create('play'))
                            ) !!}

                        @if (!Auth::user()->hasFeature(FEATURE_REPORTS))
                        <script>
                            $(function() {   
                                $('form.warn-on-exit').find('input, button').prop('disabled', true);
                            });
                        </script>   
                        @endif


                    </div>
                    <div class="col-md-6">
                        {!! Former::checkbox('enable_report')->text(trans('texts.enable')) !!}
                        {!! Former::select('report_type')->options($reportTypes, $reportType)->label(trans('texts.type')) !!}
                        <div id="dateField" style="display:{{ $reportType == ENTITY_TAX_RATE ? 'block' : 'none' }}">
                            {!! Former::select('date_field')->label(trans('texts.filter'))
                                    ->addOption(trans('texts.invoice_date'), FILTER_INVOICE_DATE)
                                    ->addOption(trans('texts.payment_date'), FILTER_PAYMENT_DATE) !!}
                        </div>
                        <p>&nbsp;</p>
                        {!! Former::checkbox('enable_chart')->text(trans('texts.enable')) !!}
                        {!! Former::select('group_by')->options($dateTypes, $groupBy) !!}
                        {!! Former::select('chart_type')->options($chartTypes, $chartType) !!}

            			
			 {!! Former::close() !!}
        </div>
        </div>

	</div>
    </div>
        @if ($enableReport)
        <div class="panel panel-default">
        <div class="panel-body">
        <table class="table table-striped invoice-table">
        <thead>                
            <tr>
                @foreach ($columns as $column)    
                    <th>{{ trans("texts.{$column}") }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if (count($displayData))
                @foreach ($displayData as $record)
                    <tr>
                        @foreach ($record as $field)
                            <td>{!! $field !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="10" style="text-align: center">{{ trans('texts.empty_table') }}</td>
                </tr>
            @endif
        </tbody>
        </table>

        <p>&nbsp;</p>

        @if (count(array_values($reportTotals)))
        <table class="table table-striped invoice-table">
        <thead>
            <tr>
                <th>{{ trans("texts.totals") }}</th>
                @foreach (array_values($reportTotals)[0] as $key => $val)
                    <th>{{ trans("texts.{$key}") }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($reportTotals as $currencyId => $val)
                <tr>
                    <td>{!! Utils::getFromCache($currencyId, 'currencies')->name !!}</td>
                    @foreach ($val as $id => $field)
                        <td>{!! Utils::formatMoney($field, $currencyId) !!}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        </table>
        @endif

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

	<script type="text/javascript">

    function onExportClick() {
        $('#action').val('export');
        $('#submitButton').click();
        $('#action').val('');    
    }

    @if ($enableChart)
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

        new Chart(ctx).{!! $chartType !!}(chart, options);
    @endif

    $(function() {
        $('.start_date .input-group-addon').click(function() {
            toggleDatePicker('start_date');
        });
        $('.end_date .input-group-addon').click(function() {
            toggleDatePicker('end_date');
        });

        $('#report_type').change(function() {
            var val = $('#report_type').val();
            if (val == '{{ ENTITY_TAX_RATE }}') {
                $('#dateField').fadeIn();
            } else {
                $('#dateField').fadeOut();
            }
        });
    })


	</script>

@stop


@section('onReady')

	$('#start_date, #end_date').datepicker({
		autoclose: true,
		todayHighlight: true,
		keyboardNavigation: false
	});

@stop