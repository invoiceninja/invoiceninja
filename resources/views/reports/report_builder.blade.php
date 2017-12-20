@extends('header')

@section('head')
	@parent

    <script src="{{ asset('js/daterangepicker.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/daterangepicker.css') }}" rel="stylesheet" type="text/css"/>

    <link href="{{ asset('css/tablesorter.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/tablesorter.min.js') }}" type="text/javascript"></script>

	<link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

	<style type="text/css">
		table.tablesorter th {
			color: white;
			background-color: #777 !important;
		}
		.select2-selection {
			background-color: #f9f9f9 !important;
			width: 100%;
		}

	</style>

@stop

@section('top-right')
	{!! Button::normal(trans('texts.calendar'))
			->asLinkTo(url('/calendar'))
			->appendIcon(Icon::create('calendar')) !!}
@stop


@section('content')

	@if (!Utils::isPro())
	    <div class="alert alert-warning" style="font-size:larger;">
	    <center>
	        {!! trans('texts.pro_plan_reports', ['link'=>'<a href="javascript:showUpgradeModal()">' . trans('texts.pro_plan_remove_logo_link') . '</a>']) !!}
	    </center>
	    </div>
	@endif

    <script type="text/javascript">

		var chartStartDate = moment("{{ $startDate }}");
		var chartEndDate = moment("{{ $endDate }}");
		var dateRanges = {!! $account->present()->dateRangeOptions !!};

		function resolveRange(range) {
			if (range == "{{ trans('texts.this_month') }}") {
				return 'this_month';
			} else if (range == "{{ trans('texts.last_month') }}") {
				return 'last_month';
			} else if (range == "{{ trans('texts.this_year') }}") {
				return 'this_year';
			} else if (range == "{{ trans('texts.last_year') }}") {
				return 'last_year';
			} else {
				return '';
			}
		}

        $(function() {

			if (isStorageSupported()) {
				var lastRange = localStorage.getItem('last:report_range');
				$('#range').val(resolveRange(lastRange));
				lastRange = dateRanges[lastRange];
				if (lastRange) {
					chartStartDate = lastRange[0];
					chartEndDate = lastRange[1];
				}
			}

            // Initialize date range selector
            function cb(start, end, label) {
                $('#reportrange span').html(start.format('{{ $account->getMomentDateFormat() }}') + ' - ' + end.format('{{ $account->getMomentDateFormat() }}'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
				if (label) {
					$('#range').val(resolveRange(label));
				}

				if (isStorageSupported() && label && label != "{{ trans('texts.custom_range') }}") {
					localStorage.setItem('last:report_range', label);
				}
            }

            $('#reportrange').daterangepicker({
                locale: {
					format: "{{ $account->getMomentDateFormat() }}",
					customRangeLabel: "{{ trans('texts.custom_range') }}",
					applyLabel: "{{ trans('texts.apply') }}",
					cancelLabel: "{{ trans('texts.cancel') }}",
                },
                startDate: chartStartDate,
                endDate: chartEndDate,
                linkedCalendars: false,
				ranges: dateRanges,
            }, cb);

            cb(chartStartDate, chartEndDate);

        });

    </script>


    {!! Former::open()->addClass('report-form')->rules(['start_date' => 'required', 'end_date' => 'required']) !!}


    <div style="display:none">
		{!! Former::text('action')->forceValue('') !!}
		{!! Former::text('range')->forceValue('') !!}
		{!! Former::text('scheduled_report_id')->forceValue('') !!}
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

						<div id="statusField" style="display:none">

							<div class="form-group">
								<label for="status_ids" class="control-label col-lg-4 col-sm-4">{{ trans('texts.status') }}</label>
								<div class="col-lg-8 col-sm-8">
									<select name="status_ids[]" class="form-control" style="width: 100%;" id="statuses_{{ ENTITY_INVOICE }}" multiple="true">
							            @foreach (\App\Models\EntityModel::getStatusesFor(ENTITY_INVOICE) as $key => $value)
							                <option value="{{ $key }}">{{ $value }}</option>
							            @endforeach
									</select>
								</div>
							</div>
						</div>

						<div id="dateField" style="display:none">
                            {!! Former::select('date_field')->label(trans('texts.filter'))
                                    ->addOption(trans('texts.invoice_date'), FILTER_INVOICE_DATE)
                                    ->addOption(trans('texts.payment_date'), FILTER_PAYMENT_DATE) !!}
                        </div>

						<div id="currencyType" style="display:none">
                            {!! Former::select('currency_type')->label(trans('texts.currency'))
                                    ->addOption(trans('texts.default'), 'default')
                                    ->addOption(trans('texts.converted'), 'converted') !!}
                        </div>

						<div id="invoiceOrExpenseField" style="display:none">
							{!! Former::select('document_filter')->label('filter')
								->addOption(trans('texts.all'), '')
									->addOption(trans('texts.invoice'), 'invoice')
									->addOption(trans('texts.expense'), 'expense') !!}
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
			$('form.report-form').find('input, button').prop('disabled', true);
		});
	</script>
	@endif


	<center class="buttons form-inline">
		<span class="well" style="padding-right:8px; padding-left:14px;">
		{!! Former::select('format')
					->addOption('CSV', 'csv')
					->addOption('XLSX', 'xlsx')
					->addOption('PDF', 'pdf')
					->raw() !!} &nbsp;

		{!! Button::normal(trans('texts.export'))
				->withAttributes(['onclick' => 'onExportClick()'])
				->appendIcon(Icon::create('download-alt')) !!}

		{!! Button::normal(trans('texts.cancel_schedule'))
				->withAttributes(['id' => 'cancelSchduleButton', 'onclick' => 'onCancelScheduleClick()', 'style' => 'display:none'])
				->appendIcon(Icon::create('remove')) !!}

		{!! Button::primary(trans('texts.schedule'))
				->withAttributes(['id'=>'scheduleButton', 'onclick' => 'showScheduleModal()', 'style' => 'display:none'])
				->appendIcon(Icon::create('time')) !!}

	 	</span> &nbsp;&nbsp;
		{!! Button::success(trans('texts.run'))
				->withAttributes(array('id' => 'submitButton'))
				->submit()
				->appendIcon(Icon::create('play'))
				->large() !!}
	</center>

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
				{!! $report ? $report->tableHeader() : '' !!}
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

		<br/>
		<div style="color:#888888">
			{{ trans('texts.reports_help') }}
		</div>

        </div>
        </div>

	</div>

	@endif

	<div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
              <h4 class="modal-title" id="myModalLabel">{{ trans('texts.scheduled_report') }}</h4>
            </div>

            <div class="container" style="width: 100%; padding-bottom: 0px !important">
            <div class="panel panel-default">
            <div class="panel-body">

				<center style="padding-bottom:40px;font-size:16px;">
					<div id="scheduleHelp"></div>
				</center>

				{!! Former::select('frequency')
							->addOption(trans('texts.freq_daily'), REPORT_FREQUENCY_DAILY)
							->addOption(trans('texts.freq_weekly'), REPORT_FREQUENCY_WEEKLY)
							->addOption(trans('texts.freq_biweekly'), REPORT_FREQUENCY_BIWEEKLY)
							->addOption(trans('texts.freq_monthly'), REPORT_FREQUENCY_MONTHLY)
							->value('weekly') !!} &nbsp;

				{!! Former::text('send_date')
						->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
						->label('start_date')
						->appendIcon('calendar')
						->placeholder('')
						->addGroupClass('send-date') !!}

            </div>
            </div>
            </div>

            <div class="modal-footer" id="signUpFooter" style="margin-top: 0px">
              <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }} </button>
              <button type="button" class="btn btn-success" onclick="onScheduleClick()">{{ trans('texts.schedule') }} </button>
            </div>
          </div>
        </div>
    </div>

	{!! Former::close() !!}


	<script type="text/javascript">

	var scheduledReports = {!! $scheduledReports !!};
	var scheduledReportMap = {};

	for (var i=0; i<scheduledReports.length; i++) {
		var schedule = scheduledReports[i];
		var config = JSON.parse(schedule.config);
		scheduledReportMap[config.report_type] = schedule.public_id;
	}

	function showScheduleModal() {
		var help = "{{ trans('texts.scheduled_report_help') }}";
		help = help.replace(':email', "{{ auth()->user()->email }}");
		help = help.replace(':format', $("#format").val().toUpperCase());
		help = help.replace(':report', $("#report_type option:selected").text());
		$('#scheduleHelp').text(help);
        $('#scheduleModal').modal('show');
    }

	function onExportClick() {
        $('#action').val('export');
        $('#submitButton').click();
		$('#action').val('');
    }

	function onScheduleClick(frequency) {
        $('#action').val('schedule');
        $('#submitButton').click();
		$('#action').val('');
    }

	function onCancelScheduleClick() {
		sweetConfirm(function() {
			var reportType = $('#report_type').val();
			$('#action').val('cancel_schedule');
			$('#frequency').val(frequency);
			$('#scheduled_report_id').val(scheduledReportMap[reportType]);
	        $('#submitButton').click();
			$('#action').val('');
		});
	}

	function setFiltersShown() {
		var val = $('#report_type').val();
		$('#dateField').toggle(val == '{{ ENTITY_TAX_RATE }}');
		$('#statusField').toggle(val == '{{ ENTITY_INVOICE }}' || val == '{{ ENTITY_PRODUCT }}');
		$('#invoiceOrExpenseField').toggle(val == '{{ ENTITY_DOCUMENT }}');
		$('#currencyType').toggle(val == '{{ ENTITY_PAYMENT }}');
	}

	function setDocumentZipShown() {
		var val = $('#report_type').val();
		var showOption = ['invoice', 'quote', 'expense', 'document'].indexOf(val) >= 0;
		var numOptions = $('#format option').size();
		if (showOption && numOptions == 3) {
			$("#format").append(new Option("ZIP - {{ trans('texts.documents') }}", 'zip'));
		} else if (! showOption && numOptions == 4) {
			$("#format option:last").remove();
		}
	}

	function setScheduleButton() {
		var reportType = $('#report_type').val();
		$('#scheduleButton').toggle(! scheduledReportMap[reportType]);
		$('#cancelSchduleButton').toggle(!! scheduledReportMap[reportType]);
	}

	var sumColumns = [];
	@foreach ($columns as $column)
		sumColumns.push("{{ in_array($column, ['amount', 'paid', 'balance', 'cost', 'duration']) ? trans("texts.{$column}") : false }}");
	@endforeach

    $(function() {
        $('.start_date .input-group-addon').click(function() {
            toggleDatePicker('start_date');
        });
        $('.end_date .input-group-addon').click(function() {
            toggleDatePicker('end_date');
        });

		$('#document_filter').change(function() {
			var val = $('#document_filter').val();
            if (isStorageSupported()) {
                localStorage.setItem('last:document_filter', val);
            }
        });

		$('#format').change(function() {
			@if (! auth()->user()->hasFeature(FEATURE_REPORTS))
				return;
			@endif
			var val = $('#format').val();
			$('#scheduleButton').prop('disabled', val == 'zip');
            if (isStorageSupported() && val != 'zip') {
                localStorage.setItem('last:report_format', val);
            }
        });

        $('#report_type').change(function() {
			@if (! auth()->user()->hasFeature(FEATURE_REPORTS))
				return;
			@endif
			var val = $('#report_type').val();
			setFiltersShown();
			setDocumentZipShown();
			setScheduleButton();
			$('#scheduleButton').prop('disabled', $('#format').val() == 'zip');
            if (isStorageSupported()) {
                localStorage.setItem('last:report_type', val);
            }
        });

		// parse 1,000.00 or 1.000,00
		function convertStringToNumber(str) {
			str = str + '' || '';
			if (str.indexOf(':') >= 0) {
				return roundToTwo(moment.duration(str).asHours());
			} else {
				var number = Number(str.replace(/[^0-9\-]+/g, ''));
				return number / 100;
			}
		}

		$(function(){
			var statusIds = isStorageSupported() ? (localStorage.getItem('last:report_status_ids') || '') : '';
			$('#statuses_{{ ENTITY_INVOICE }}').select2({
				//allowClear: true,
			}).val(statusIds.split(',')).trigger('change')
			  	.on('change', function() {
					if (isStorageSupported()) {
						var filter = $('#statuses_{{ ENTITY_INVOICE }}').val();
						if (filter) {
							filter = filter.join(',');
						} else {
							filter = '';
						}
						localStorage.setItem('last:report_status_ids', filter);
					}
				}).maximizeSelect2Height();

  			$(".tablesorter-data").tablesorter({
				@if (! request()->group_when_sorted)
					sortList: [[0,0]],
				@endif
				theme: 'bootstrap',
				widgets: ['zebra', 'uitheme', 'filter'{!! request()->group_when_sorted ? ", 'group'" : "" !!}, 'columnSelector'],
				headerTemplate : '{content} {icon}',
				@if ($report)
					dateFormat: '{{ $report->convertDateFormat() }}',
				@endif
				numberSorter: function(a, b, direction) {
					var a = convertStringToNumber(a);
					var b = convertStringToNumber(b);
					return direction ? a - b : b - a;
				},
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
				            var txt = $(this).find("td").eq(i).text();
				            subtotal += convertStringToNumber(txt);
				          });
				          $cell.find(".group-count").append(' - ' + label + ': ' + roundToTwo(subtotal, true));
					  }
			        },
			    }
			}).show();

			$(".tablesorter-totals").tablesorter({
				theme: 'bootstrap',
				widgets: ['zebra', 'uitheme'],
			}).show();

			setFiltersShown();
			setDocumentZipShown();
			setTimeout(function() {
				setScheduleButton();
			}, 1);

			if (isStorageSupported()) {
				var lastReportType = localStorage.getItem('last:report_type');
				if (lastReportType) {
					$('#report_type').val(lastReportType);
				}
				var lastDocumentFilter = localStorage.getItem('last:document_filter');
				if (lastDocumentFilter) {
					$('#document_filter').val(lastDocumentFilter);
				}
				var lastFormat = localStorage.getItem('last:report_format');
				if (lastFormat) {
					setTimeout(function() {
						$('#format').val(lastFormat);
					}, 1);
				}
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

	var currentDate = new Date();
	currentDate.setDate(currentDate.getDate() + 1);
	$('#send_date').datepicker('update', currentDate);

@stop
