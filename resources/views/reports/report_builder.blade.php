@extends('header')

@section('head')
	@parent

	@include('money_script')

	<script src="{{ asset('js/Chart.min.js') }}" type="text/javascript"></script>

    <script src="{{ asset('js/daterangepicker.min.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
    <link href="{{ asset('css/daterangepicker.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

    <link href="{{ asset('css/tablesorter.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/tablesorter.min.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

	<link href="{{ asset('css/select2.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/select2.min.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

	<style type="text/css">
		table.tablesorter th {
			color: white;
			background-color: #777 !important;
		}
		.select2-selection {
			background-color: #f9f9f9 !important;
			width: 100%;
		}

		.tablesorter-column-selector label {
			display: block;
		}

		.tablesorter-column-selector input {
			margin-right: 8px;
		}
	</style>

@stop

@section('top-right')
	@if (config('services.postmark') && auth()->user()->hasPermission('view_reports'))
		{!! Button::normal(trans('texts.emails'))
				->asLinkTo(url('/reports/emails'))
				->appendIcon(Icon::create('envelope')) !!}
	@endif
	{!! Button::normal(trans('texts.calendar'))
			->asLinkTo(url('/reports/calendar'))
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

				if (isStorageSupported() && label) {
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

	<div class="row">
		<div class="col-lg-12">
            <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.report_settings') !!}</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">

						{!! Former::select('report_type')
								->data_bind("options: report_types, optionsText: 'transType', optionsValue: 'type', value: report_type")
								->label(trans('texts.type')) !!}

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

                    </div>
                    <div class="col-md-6">

						{!! Former::select('group')
									->data_bind("options: groups, optionsText: 'transPeriod', optionsValue: 'period', value: group") !!}

						<span data-bind="visible: showSubgroup">
							{!! Former::select('subgroup')
										->data_bind("options: subgroups, optionsText: 'transField', optionsValue: 'field', value: subgroup") !!}
						</span>

						<div id="statusField" style="display:none" data-bind="visible: showStatus">
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

						<div id="dateField" style="display:none" data-bind="visible: showInvoiceOrPaymentDate">
                            {!! Former::select('date_field')->label(trans('texts.filter'))
                                    ->addOption(trans('texts.invoice_date'), FILTER_INVOICE_DATE)
                                    ->addOption(trans('texts.payment_date'), FILTER_PAYMENT_DATE) !!}
                        </div>

						<div id="currencyType" style="display:none" data-bind="visible: showCurrencyType">
                            {!! Former::select('currency_type')->label(trans('texts.currency'))
                                    ->addOption(trans('texts.default'), 'default')
                                    ->addOption(trans('texts.converted'), 'converted') !!}
                        </div>

						<div id="invoiceOrExpenseField" style="display:none" data-bind="visible: showInvoiceOrExpense">
							{!! Former::select('document_filter')->label('filter')
								->addOption(trans('texts.all'), '')
									->addOption(trans('texts.invoice'), 'invoice')
									->addOption(trans('texts.expense'), 'expense') !!}
						</div>

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
					->data_bind("options: export_formats, optionsText: 'transFormat', optionsValue: 'format', value: export_format")
					->raw() !!} &nbsp;

		{!! Button::normal(trans('texts.export'))
				->withAttributes(['style' => 'display:none', 'onclick' => 'onExportClick()', 'data-bind' => 'visible: showExportButton'])
				->appendIcon(Icon::create('download-alt')) !!}

		{!! Button::normal(trans('texts.cancel_schedule'))
				->withAttributes(['id' => 'cancelSchduleButton', 'onclick' => 'onCancelScheduleClick()', 'style' => 'display:none', 'data-bind' => 'visible: showCancelScheduleButton'])
				->appendIcon(Icon::create('remove')) !!}

		{!! Button::primary(trans('texts.schedule'))
				->withAttributes(['id'=>'scheduleButton', 'onclick' => 'showScheduleModal()', 'style' => 'display:none', 'data-bind' => 'visible: showScheduleButton, css: enableScheduleButton'])
				->appendIcon(Icon::create('time')) !!}

	 	</span> &nbsp;&nbsp;

		{!! Button::success(trans('texts.run'))
				->withAttributes(array('id' => 'submitButton'))
				->submit()
				->appendIcon(Icon::create('play'))
				->large() !!}

		@if (request()->report_type)
			<button id="popover" type="button" class="btn btn-default btn-lg">
			  {{ trans('texts.columns') }}
			  {!! Icon::create('th-list') !!}
			</button>

			<div class="hidden">
			  <div id="popover-target"></div>
			</div>
		@endif

	</center>

	@if (request()->report_type)

		@include('reports.chart_builder')

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
	                    @foreach ($val as $field => $value)
							<td>
								@if ($field == 'duration')
									{{ Utils::formatTime($value) }}
								@else
		                        	{{ Utils::formatMoney($value, $currencyId) }}
								@endif
							</td>
	                    @endforeach
	                </tr>
				@endforeach
            @endforeach
        </tbody>
        </table>
		<p>&nbsp;</p>
        @endif

        <table id="{{ request()->report_type }}Report" class="tablesorter tablesorter-data" style="display:none">
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
						->addGroupClass('send-date')
						->data_date_start_date($account->formatDate($account->getDateTime())) !!}

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

	var sumColumns = [];
	@foreach ($columns as $column => $class)
		sumColumns.push("{{ in_array($column, ['amount', 'paid', 'balance', 'cost', 'duration', 'tax', 'qty']) ? trans("texts.{$column}") : false }}");
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
			if (! isStorageSupported()) {
				return;
			}
			setTimeout(function() {
				localStorage.setItem('last:report_format', model.export_format());
			}, 1);
        });

        $('#report_type').change(function() {
            if (! isStorageSupported()) {
				return;
			}
			setTimeout(function() {
				localStorage.setItem('last:report_type', model.report_type());
			}, 1);
        });

		$('#group').change(function() {
			if (! isStorageSupported()) {
				return;
			}
			setTimeout(function() {
				localStorage.setItem('last:report_group', model.group());
			}, 1);
        });

		$('#subgroup').change(function() {
			if (! isStorageSupported()) {
				return;
			}
			setTimeout(function() {
				localStorage.setItem('last:report_subgroup', model.subgroup());
			}, 1);
        });

		// parse 1,000.00 or 1.000,00
		function convertStringToNumber(str) {
			str = str + '' || '';
			if (str.indexOf(':') >= 0) {
				return roundToTwo(moment.duration(str).asHours());
			} else {
				return NINJA.parseFloat(str);
				var number = Number(str.replace(/[^0-9\-]+/g, ''));
				return number / 100;
			}
		}

		function ReportTypeModel(type, transType) {
			var self = this;
			self.type = type;
			self.transType = transType;
		}

		function ExportFormatModel(format, transFormat) {
			var self = this;
			self.format = format;
			self.transFormat = transFormat;
		}

		function GroupModel(period, transPeriod) {
			var self = this;
			self.period = period;
			self.transPeriod = transPeriod;
		}

		function SubgroupModel(field, transField) {
			var self = this;
			self.field = field;
			self.transField = transField;
		}

		function ViewModel() {
			var self = this;
			self.report_types = ko.observableArray();
			self.report_type = ko.observable();
			self.export_format = ko.observable();
			self.start_date = ko.observable();
			self.end_date = ko.observable();
			self.group = ko.observable();
			self.subgroup = ko.observable();

			@foreach ($reportTypes as $key => $val)
				self.report_types.push(new ReportTypeModel("{{ $key }}", "{{ $val}}"));
			@endforeach

			self.groups = ko.observableArray([
				new GroupModel('', ''),
				new GroupModel('day', '{{ trans('texts.day') }}'),
				new GroupModel('monthyear', '{{ trans('texts.month') }}'),
				new GroupModel('year', '{{ trans('texts.year') }}'),
			]);

			self.subgroups = ko.computed(function() {
				var reportType = self.report_type();

				var options = [
					new SubgroupModel('', '')
				];

				if (['client'].indexOf(reportType) == -1) {
					options.push(new SubgroupModel('client', "{{ trans('texts.client') }}"));
				}

				options.push(new SubgroupModel('user', "{{ trans('texts.user') }}"));

				if (reportType == 'activity') {
					options.push(new SubgroupModel('category', "{{ trans('texts.category') }}"));
				} else if (reportType == 'aging') {
					options.push(new SubgroupModel('age', "{{ trans('texts.age') }}"));
				} else if (reportType == 'expense') {
					options.push(new SubgroupModel('vendor', "{{ trans('texts.vendor') }}"));
					options.push(new SubgroupModel('category', "{{ trans('texts.category') }}"));
				} else if (reportType == 'payment') {
					options.push(new SubgroupModel('method', "{{ trans('texts.method') }}"));
				} else if (reportType == 'profit_and_loss') {
					options.push(new SubgroupModel('type', "{{ trans('texts.type') }}"));
				} else if (reportType == 'task' || reportType == 'task_details') {
					options.push(new SubgroupModel('project', "{{ trans('texts.project') }}"));
				} else if (reportType == 'client') {
					options.push(new SubgroupModel('country', "{{ trans('texts.country') }}"));
				} else if (reportType == 'invoice' || reportType == 'quote') {
					options.push(new SubgroupModel('status', "{{ trans('texts.status') }}"));
				} else if (reportType == 'product') {
					options.push(new SubgroupModel('product', "{{ trans('texts.product') }}"));
				}

				return options;
			});

			self.export_formats = ko.computed(function() {
				var options = [
					new ExportFormatModel('csv', 'CSV'),
					new ExportFormatModel('xlsx', 'XLSX'),
					//new ExportFormatModel('pdf', 'PDF'),
				]

				if (['{{ ENTITY_INVOICE }}', '{{ ENTITY_QUOTE }}', '{{ ENTITY_EXPENSE }}', '{{ ENTITY_DOCUMENT }}'].indexOf(self.report_type()) >= 0) {
					options.push(new ExportFormatModel('zip', 'ZIP - {{ trans('texts.documents') }}'));
				}

				if (['{{ ENTITY_INVOICE }}'].indexOf(self.report_type()) >= 0) {
					options.push(new ExportFormatModel('zip-invoices', 'ZIP - {{ trans('texts.invoices') }}'));
				}

				return options;
			});

			if (isStorageSupported()) {
				var lastReportType = localStorage.getItem('last:report_type');
				if (lastReportType) {
					self.report_type(lastReportType);
				}
				var lastGroup = localStorage.getItem('last:report_group');
				if (lastGroup) {
					self.group(lastGroup);
				}
				var lastSubgroup = localStorage.getItem('last:report_subgroup');
				if (lastSubgroup) {
					self.subgroup(lastSubgroup);
				}
				var lastFormat = localStorage.getItem('last:report_format');
				if (lastFormat) {
					self.export_format(lastFormat);
				}
			}

			self.showSubgroup = ko.computed(function() {
				return self.group();
			})

			self.showInvoiceOrPaymentDate = ko.computed(function() {
				return self.report_type() == '{{ ENTITY_TAX_RATE }}';
			});

			self.showStatus = ko.computed(function() {
				return ['{{ ENTITY_INVOICE }}', '{{ ENTITY_QUOTE }}', '{{ ENTITY_PRODUCT }}'].indexOf(self.report_type()) >= 0;
			});

			self.showInvoiceOrExpense = ko.computed(function() {
				return self.report_type() == '{{ ENTITY_DOCUMENT }}';
			});

			self.showCurrencyType = ko.computed(function() {
				return self.report_type() == '{{ ENTITY_PAYMENT }}';
			});

			self.enableScheduleButton = ko.computed(function() {
				return ['zip', 'zip-invoices'].indexOf(self.export_format()) >= 0 ? 'disabled' : 'enabled';
			});

			self.showScheduleButton = ko.computed(function() {
				return ! scheduledReportMap[self.report_type()];
			});

			self.showCancelScheduleButton = ko.computed(function() {
				return !! scheduledReportMap[self.report_type()];
			});

            self.showExportButton = ko.computed(function() {
                return self.export_format() != '';
            });
		}

		$(function(){
			window.model = new ViewModel();
			ko.applyBindings(model);

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
				@if (! request()->group)
					sortList: [[0,0]],
				@endif
				theme: 'bootstrap',
				widgets: ['zebra', 'uitheme', 'filter'{!! request()->group ? ", 'group'" : "" !!}, 'columnSelector'],
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
					columnSelector_mediaqueryName: "{{ trans('texts.auto') }}",
					columnSelector_mediaqueryHidden: true,
					columnSelector_saveColumns: true,
					//storage_useSessionStorage: true,
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
				            subtotal += convertStringToNumber(txt) || 0;
				          });
				          $cell.find(".group-count").append(' | ' + label + ': ' + roundToTwo(subtotal, true));
					  }
			        },
			    }
			}).show();

			@if (request()->report_type)
				$.tablesorter.columnSelector.attachTo( $('.tablesorter-data'), '#popover-target');
				$('#popover')
				.popover({
					placement: 'right',
					html: true, // required if content has HTML
					content: $('#popover-target')
				});
			@endif

			$(".tablesorter-totals").tablesorter({
				theme: 'bootstrap',
				widgets: ['zebra', 'uitheme'],
			}).show();

			if (isStorageSupported()) {
				var lastDocumentFilter = localStorage.getItem('last:document_filter');
				if (lastDocumentFilter) {
					$('#document_filter').val(lastDocumentFilter);
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
