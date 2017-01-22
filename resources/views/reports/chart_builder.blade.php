@extends('header')

@section('head')
	@parent

    <script src="{{ asset('js/daterangepicker.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/daterangepicker.css') }}" rel="stylesheet" type="text/css"/>

    <link href="{{ asset('css/tablesorter.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/tablesorter.min.js') }}" type="text/javascript"></script>

	<style type="text/css">
		table.tablesorter th {
			color: white;
			background-color: #777 !important;
		}

	</style>

@stop

@section('content')

    <script type="text/javascript">

        $(function() {

            var chartStartDate = moment("{{ $startDate }}");
            var chartEndDate = moment("{{ $endDate }}");

            // Initialize date range selector
            function cb(start, end) {
                $('#reportrange span').html(start.format('{{ $account->getMomentDateFormat() }}') + ' - ' + end.format('{{ $account->getMomentDateFormat() }}'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            }

            $('#reportrange').daterangepicker({
                locale: {
                    "format": "{{ $account->getMomentDateFormat() }}",
                },
                startDate: chartStartDate,
                endDate: chartEndDate,
                linkedCalendars: false,
                ranges: {
                   'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                   'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                   'This Month': [moment().startOf('month'), moment().endOf('month')],
                   'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                   'This Year': [moment().startOf('year'), moment().endOf('month')]
                }
            }, cb);

            cb(chartStartDate, chartEndDate);

        });

    </script>


    {!! Former::open()->rules(['start_date' => 'required', 'end_date' => 'required'])->addClass('warn-on-exit') !!}

    <div style="display:none">
    {!! Former::text('action') !!}
    </div>

    {!! Former::populateField('start_date', $startDate) !!}
    {!! Former::populateField('end_date', $endDate) !!}

	@if ( ! request()->report_type)
		{!! Former::populateField('group_when_sorted', 1) !!}
		{!! Former::populateField('group_dates_by', 'monthyear') !!}
	@endif

	<div class="row">
		<div class="col-lg-12">
            <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.report_settings') !!}</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">

						{!! Former::select('report_type')->options($reportTypes, $reportType)->label(trans('texts.type')) !!}

						<div class="form-group">
                            <label for="reportrange" class="control-label col-lg-4 col-sm-4">
                                {{ trans('texts.date_range') }}
                            </label>
                            <div class="col-lg-8 col-sm-8">
                                <div id="reportrange" style="background: #f9f9f9; cursor: pointer; padding: 9px 14px; border: 1px solid #dfe0e1; margin-top: 0px;">
                                    <i class="glyphicon glyphicon-calendar fa fa-calendar"></i>&nbsp;
                                    <span></span> <b class="caret"></b>
                                </div>

                                <div style="display:none">
                                    {!! Former::text('start_date') !!}
                                    {!! Former::text('end_date') !!}
                                </div>
                            </div>
                        </div>

						<div id="statusField" style="display:{{ in_array($reportType, ['invoice', 'invoice_details']) ? 'block' : 'none' }}">
							{!! Former::select('invoice_status')->label('status')
									->addOption(trans('texts.all'), 'all')
									->addOption(trans('texts.draft'), 'draft')
									->addOption(trans('texts.unpaid'), 'unpaid')
									->addOption(trans('texts.paid'), 'paid') !!}
						</div>

						<div id="dateField" style="display:{{ $reportType == ENTITY_TAX_RATE ? 'block' : 'none' }}">
                            {!! Former::select('date_field')->label(trans('texts.filter'))
                                    ->addOption(trans('texts.invoice_date'), FILTER_INVOICE_DATE)
                                    ->addOption(trans('texts.payment_date'), FILTER_PAYMENT_DATE) !!}
                        </div>

                    </div>
                    <div class="col-md-6">

						{!! Former::checkbox('group_when_sorted')->text('enable') !!}
						{!! Former::select('group_dates_by')
									->addOption(trans('texts.day'), 'day')
									->addOption(trans('texts.month'), 'monthyear')
									->addOption(trans('texts.year'), 'year') !!}

        		  </div>
        </div>
	</div>
    </div>

	@if (!Auth::user()->hasFeature(FEATURE_REPORTS))
	<script>
		$(function() {
			$('form.warn-on-exit').find('input, button').prop('disabled', true);
		});
	</script>
	@endif


	<center>
		{!! Button::primary(trans('texts.export'))
				->withAttributes(array('onclick' => 'onExportClick()'))
				->appendIcon(Icon::create('export'))
				->large() !!}
		{!! Button::success(trans('texts.run'))
				->withAttributes(array('id' => 'submitButton'))
				->submit()
				->appendIcon(Icon::create('play'))
				->large() !!}
	</center><br/>


	{!! Former::close() !!}


	@if (request()->report_type)
        <div class="panel panel-default">
        <div class="panel-body">

        @if (count(array_values($reportTotals)))
        <table class="tablesorter tablesorter-totals" style="display:none">
        <thead>
            <tr>
                <th>{{ trans("texts.totals") }}</th>
				@foreach (array_values(array_values($reportTotals)[0])[0] as $key => $val)
                    <th>{{ trans("texts.{$key}") }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($reportTotals as $currencyId => $each)
				@foreach ($each as $dimension => $val)
	                <tr>
	                    <td>{!! Utils::getFromCache($currencyId, 'currencies')->name !!}
						@if ($dimension)
							- {{ $dimension }}
						@endif
						</td>
	                    @foreach ($val as $id => $field)
	                        <td>{!! Utils::formatMoney($field, $currencyId) !!}</td>
	                    @endforeach
	                </tr>
				@endforeach
            @endforeach
        </tbody>
        </table>
		<p>&nbsp;</p>
        @endif

		<!--
		<div class="columnSelectorWrapper">
		  <input id="colSelect1" type="checkbox" class="hidden">
		  <label class="columnSelectorButton" for="colSelect1">Column</label>

		  <div id="columnSelector" class="columnSelector">
		  </div>
		</div>
		-->

        <table class="tablesorter tablesorter-data" style="display:none">
        <thead>
            <tr>
				{!! $report->tableHeader() !!}
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

        </div>
        </div>

	</div>

	@endif

	<script type="text/javascript">

    function onExportClick() {
        $('#action').val('export');
        $('#submitButton').click();
        $('#action').val('');
    }

	var sumColumns = [];
	@foreach ($columns as $column)
		sumColumns.push("{{ in_array($column, ['amount', 'paid', 'balance']) ? trans("texts.{$column}") : false }}");
	@endforeach

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
			if (val == '{{ ENTITY_INVOICE }}' || val == 'invoice_details') {
                $('#statusField').fadeIn();
            } else {
                $('#statusField').fadeOut();
            }
        });

		$(function(){
  			$(".tablesorter-data").tablesorter({
				theme: 'bootstrap',
				widgets: ['zebra', 'uitheme', 'filter'{!! request()->group_when_sorted ? ", 'group'" : "" !!}, 'columnSelector'],
				headerTemplate : '{content} {icon}',
				widgetOptions : {
					columnSelector_container : $('#columnSelector'),
					filter_cssFilter: 'form-control',
					group_collapsed: true,
					group_saveGroups: false,
					//group_formatter   : function(txt, col, table, c, wo, data) {},
					group_callback: function ($cell, $rows, column, table) {
					  for (var i=0; i<sumColumns.length; i++) {
						  var label = sumColumns[i];
						  if (!label) {
							  continue;
						  }
						  var subtotal = 0;
				          $rows.each(function() {
				            var txt = $(this).find("td").eq(i).text().replace(/[$,]/g, '');
				            subtotal += parseFloat(txt || 0);
				          });
				          $cell.find(".group-count").append(' - ' + label + ': ' + roundToTwo(subtotal));
					  }
			        },
			    }
			}).show();

			$(".tablesorter-totals").tablesorter({
				theme: 'bootstrap',
				widgets: ['zebra', 'uitheme'],
			}).show();
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
