@extends('header')

@section('head')
	@parent

    @include('money_script')

	<script src="{!! asset('js/Chart.min.js') !!}" type="text/javascript"></script>
    <script src="{{ asset('js/daterangepicker.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/daterangepicker.css') }}" rel="stylesheet" type="text/css"/>

@stop

@section('content')

<script type="text/javascript">

    @if (Auth::user()->hasPermission('view_all'))
        function loadChart(data) {
            var ctx = document.getElementById('chart-canvas').getContext('2d');
            if (window.myChart) {
                window.myChart.config.data = data;
                window.myChart.config.options.scales.xAxes[0].time.unit = chartGroupBy.toLowerCase();
                window.myChart.config.options.scales.xAxes[0].time.round = chartGroupBy.toLowerCase();
                window.myChart.update();
            } else {
                $('#progress-div').hide();
                $('#chart-canvas').fadeIn();
                window.myChart = new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        tooltips: {
                            mode: 'x-axis',
                            titleFontSize: 15,
                            titleMarginBottom: 12,
                            bodyFontSize: 15,
                            bodySpacing: 10,
                            callbacks: {
                                title: function(item) {
                                    return moment(item[0].xLabel).format("{{ $account->getMomentDateFormat() }}");
                                },
                                label: function(item, data) {
                                    if (item.datasetIndex == 0) {
                                        var label = " {{ trans('texts.invoices') }}: ";
                                    } else if (item.datasetIndex == 1) {
                                        var label = " {{ trans('texts.payments') }}: ";
                                    } else if (item.datasetIndex == 2) {
                                        var label = " {{ trans('texts.expenses') }}: ";
                                    }

                                    return label + formatMoney(item.yLabel, chartCurrencyId, account.country_id);
                                }
                            }
                        },
                        title: {
                            display: false,
                            fontSize: 18,
                            text: '{{ trans('texts.total_revenue') }}'
                        },
                        scales: {
                            xAxes: [{
                                type: 'time',
                                time: {
                                    unit: chartGroupBy,
                                    round: chartGroupBy,
                                },
                                gridLines: {
                                    display: false,
                                },
                            }],
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    callback: function(label, index, labels) {
                                        return formatMoney(label, chartCurrencyId, account.country_id);
                                    }
                                },
                            }]
                        }
                    }
                });
            }
        }

        var account = {!! $account !!};
        var chartGroupBy = 'day';
        var chartCurrencyId = {{ $account->getCurrencyId() }};
		var dateRanges = {!! $account->present()->dateRangeOptions !!};
		var chartStartDate;
        var chartEndDate;

        $(function() {

            // Initialize date range selector
			chartStartDate = moment().subtract(29, 'days');
	        chartEndDate = moment();
			lastRange = false;

			if (isStorageSupported()) {
				lastRange = localStorage.getItem('last:dashboard_range');
				dateRange = dateRanges[lastRange];

				if (dateRange) {
					chartStartDate = dateRange[0];
					chartEndDate = dateRange[1];
				}

				@if (count($currencies) > 1)
					var currencyId = localStorage.getItem('last:dashboard_currency_id');
					if (currencyId) {
						chartCurrencyId = currencyId;
						$("#currency-btn-group [data-button=\"" + chartCurrencyId + "\"]").addClass("active").siblings().removeClass("active");
					}
				@endif

				var groupBy = localStorage.getItem('last:dashboard_group_by');
				if (groupBy) {
					chartGroupBy = groupBy;
					$("#group-btn-group [data-button=\"" + groupBy + "\"]").addClass("active").siblings().removeClass("active");
				}
			}

            function cb(start, end, label) {
                $('#reportrange span').html(start.format('{{ $account->getMomentDateFormat() }}') + ' - ' + end.format('{{ $account->getMomentDateFormat() }}'));
                chartStartDate = start;
                chartEndDate = end;
				$('.range-label-div').show();
				if (label) {
					$('.range-label-div').text(label);
				}
                loadData();

				if (isStorageSupported() && label && label != "{{ trans('texts.custom_range') }}") {
					localStorage.setItem('last:dashboard_range', label);
				}
            }

            $('#reportrange').daterangepicker({
                locale: {
                    format: "{{ $account->getMomentDateFormat() }}",
					customRangeLabel: "{{ trans('texts.custom_range') }}",
                },
				startDate: chartStartDate,
                endDate: chartEndDate,
                linkedCalendars: false,
                ranges: dateRanges,
            }, cb);

            cb(chartStartDate, chartEndDate, lastRange);

            $("#currency-btn-group > .btn").click(function(){
                $(this).addClass("active").siblings().removeClass("active");
                chartCurrencyId = currencyMap[$(this).text()].id;
                loadData();
				if (isStorageSupported()) {
					localStorage.setItem('last:dashboard_currency_id', $(this).attr('data-button'));
				}
            });

            $("#group-btn-group > .btn").click(function(){
                $(this).addClass("active").siblings().removeClass("active");
                chartGroupBy = $(this).attr('data-button');
                loadData();
				if (isStorageSupported()) {
					localStorage.setItem('last:dashboard_group_by', chartGroupBy);
				}
            });

            function loadData() {
                var includeExpenses = "{{ count($expenses) ? 'true' : 'false' }}";
                var url = "{!! url('/dashboard_chart_data') !!}/" + chartGroupBy + '/' + chartStartDate.format('YYYY-MM-DD') + '/' + chartEndDate.format('YYYY-MM-DD') + '/' + chartCurrencyId + '/' + includeExpenses;
                $.get(url, function(response) {
                    response = JSON.parse(response);
                    loadChart(response.data);

                    var totals = response.totals;
                    $('.revenue-div').text(formatMoney(totals.revenue, chartCurrencyId, account.country_id));
                    $('.outstanding-div').text(formatMoney(totals.balance, chartCurrencyId, account.country_id));
                    $('.expenses-div').text(formatMoney(totals.expenses, chartCurrencyId, account.country_id));
                    $('.average-div').text(formatMoney(totals.average, chartCurrencyId, account.country_id));

                    $('.currency').hide();
                    $('.currency_' + chartCurrencyId).show();

					// add blank values to fix layout
					var divs = ['revenue', 'expenses', 'outstanding']
					for (var i=0; i<divs.length; i++) {
						var type = divs[i];
						if (!$('.' + type + '-panel .currency_' + chartCurrencyId).length) {
							$('.' + type + '-panel .currency_blank').text(formatMoney(0, chartCurrencyId)).show();
						}
					}
                })
            }

        });
    @else
        $(function() {
            $('.currency').show();
        })
    @endif

</script>

<div class="row">
    <div class="col-md-2">
        <ol class="breadcrumb"><li class='active'>{{ trans('texts.dashboard') }}</li></ol>
    </div>
    @if (count($tasks))
        <div class="col-md-2" style="padding-top:6px">
            @foreach ($tasks as $task)
                {!! Button::primary($task->present()->titledName)->small()->asLinkTo($task->present()->url) !!}
            @endforeach
        </div>
        <div class="col-md-8">
    @else
        <div class="col-md-10">
    @endif
        @if (Auth::user()->hasPermission('view_all'))
        <div class="pull-right">
            @if (count($currencies) > 1)
            <div id="currency-btn-group" class="btn-group" role="group" style="border: 1px solid #ccc;">
              @foreach ($currencies as $key => $val)
                <button type="button" class="btn btn-normal {{ array_values($currencies)[0] == $val ? 'active' : '' }}"
                    data-button="{{ $key }}" style="font-weight:normal !important;background-color:white">{{ $val }}</button>
              @endforeach
            </div>
            @endif
            <div id="group-btn-group" class="btn-group" role="group" style="border: 1px solid #ccc; margin-left:18px">
              <button type="button" class="btn btn-normal active" data-button="day" style="font-weight:normal !important;background-color:white">{{ trans('texts.day') }}</button>
              <button type="button" class="btn btn-normal" data-button="week" style="font-weight:normal !important;background-color:white">{{ trans('texts.week') }}</button>
              <button type="button" class="btn btn-normal" data-button="month" style="font-weight:normal !important;background-color:white">{{ trans('texts.month') }}</button>
            </div>
            <div id="reportrange" class="pull-right" style="background: #fff; cursor: pointer; padding: 9px 14px; border: 1px solid #ccc; margin-top: 0px; margin-left:18px">
                <i class="glyphicon glyphicon-calendar fa fa-calendar"></i>&nbsp;
                <span></span> <b class="caret"></b>
            </div>
        </div>
        @endif
    </div>
</div>

@if ($account->company->hasEarnedPromo())
	@include('partials/discount_promo')
@elseif ($showBlueVinePromo)
    @include('partials/bluevine_promo')
@endif

@if ($showWhiteLabelExpired)
	@include('partials/white_label_expired')
@endif

<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body revenue-panel">
                <div style="overflow:hidden">
                    <div class="{{ $headerClass }}">
                        {{ trans('texts.total_revenue') }}
                    </div>
                    <div class="revenue-div in-bold pull-right" style="color:#337ab7">
                    </div>
                    <div class="in-bold">
                        @if (count($paidToDate))
                            @foreach ($paidToDate as $item)
                                <div class="currency currency_{{ $item->currency_id ?: $account->getCurrencyId() }}" style="display:none">
                                    {{ Utils::formatMoney($item->value, $item->currency_id) }}
                                </div>
                            @endforeach
                        @else
                            <div class="currency currency_{{ $account->getCurrencyId() }}" style="display:none">
                                {{ Utils::formatMoney(0) }}
                            </div>
                        @endif
						<div class="currency currency_blank" style="display:none">
							&nbsp;
						</div>
                    </div>
					<div class="range-label-div {{ $footerClass }} pull-right" style="color:#337ab7;font-size:16px;display:none;">
						{{ trans('texts.last_30_days') }}
					</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body expenses-panel">
                <div style="overflow:hidden">
                    @if (count($expenses))
                        <div class="{{ $headerClass }}">
                            {{ trans('texts.total_expenses') }}
                        </div>
                        <div class="expenses-div in-bold pull-right" style="color:#337ab7">
                        </div>
                        <div class="in-bold">
                            @foreach ($expenses as $item)
                                <div class="currency currency_{{ $item->currency_id ?: $account->getCurrencyId() }}" style="display:none">
                                    {{ Utils::formatMoney($item->value, $item->currency_id) }}<br/>
                                </div>
                            @endforeach
							<div class="currency currency_blank" style="display:none">
								&nbsp;
							</div>
                        </div>
                    @else
                        <div class="{{ $headerClass }}">
                            {{ trans('texts.average_invoice') }}
                        </div>
                        <div class="average-div in-bold pull-right" style="color:#337ab7">
                        </div>
                        <div class="in-bold">
                            @if (count($averageInvoice))
                                @foreach ($averageInvoice as $item)
                                    <div class="currency currency_{{ $item->currency_id ?: $account->getCurrencyId() }}" style="display:none">
                                        {{ Utils::formatMoney($item->invoice_avg, $item->currency_id) }}<br/>
                                    </div>
                                @endforeach
                            @else
                                <div class="currency currency_{{ $account->getCurrencyId() }}" style="display:none">
                                    {{ Utils::formatMoney(0) }}
                                </div>
                            @endif
							<div class="currency currency_blank" style="display:none">
								&nbsp;
							</div>
                        </div>
                    @endif
					<div class="range-label-div {{ $footerClass }} pull-right" style="color:#337ab7;font-size:16px;display:none;">
						{{ trans('texts.last_30_days') }}
					</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body outstanding-panel">
                <div style="overflow:hidden">
                    <div class="{{ $headerClass }}">
                        {{ trans('texts.outstanding') }}
                    </div>
                    <div class="outstanding-div in-bold pull-right" style="color:#337ab7">
                    </div>
                    <div class="in-bold">
                        @if (count($balances))
                            @foreach ($balances as $item)
                                <div class="currency currency_{{ $item->currency_id ?: $account->getCurrencyId() }}" style="display:none">
                                    {{ Utils::formatMoney($item->value, $item->currency_id) }}<br/>
                                </div>
                            @endforeach
                        @else
                            <div class="currency currency_{{ $account->getCurrencyId() }}" style="display:none">
                                {{ Utils::formatMoney(0) }}
                            </div>
                        @endif
						<div class="currency currency_blank" style="display:none">
							&nbsp;
						</div>
                    </div>
					<div class="range-label-div {{ $footerClass }} pull-right" style="color:#337ab7;font-size:16px;display:none;">
						{{ trans('texts.last_30_days') }}
					</div>
                </div>
            </div>
        </div>
    </div>
</div>

@if (Auth::user()->hasPermission('view_all'))
<div class="row">
    <div class="col-md-12">
        <div id="progress-div" class="progress">
            <div class="progress-bar progress-bar-striped active" role="progressbar"
                aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
        </div>
        <canvas id="chart-canvas" height="70px" style="background-color:white;padding:20px;display:none"></canvas>
    </div>
</div>
<p>&nbsp;</p>
@endif

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default dashboard" style="height:320px">
            <div class="panel-heading" style="background-color:#286090 !important">
                <h3 class="panel-title in-bold-white">
                    <i class="glyphicon glyphicon-exclamation-sign"></i> {{ trans('texts.activity') }}
                    @if ($invoicesSent)
                        <div class="pull-right" style="font-size:14px;padding-top:4px">
							@if ($invoicesSent == 1)
								{{ trans('texts.invoice_sent', ['count' => $invoicesSent]) }}
							@else
								{{ trans('texts.invoices_sent', ['count' => $invoicesSent]) }}
							@endif
                        </div>
                    @endif
                </h3>
            </div>
            <ul class="panel-body list-group" style="height:276px;overflow-y:auto;">
                @foreach ($activities as $activity)
                <li class="list-group-item">
                    <span style="color:#888;font-style:italic">{{ Utils::timestampToDateString(strtotime($activity->created_at)) }}:</span>
                    {!! $activity->getMessage() !!}
                </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default dashboard" style="height:320px;">
            <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">
                <h3 class="panel-title" style="color: black !important">
                    @if (count($expenses) && count($averageInvoice))
                        <div class="pull-right" style="font-size:14px;padding-top:4px;font-weight:bold">
                            @foreach ($averageInvoice as $item)
                                <span class="currency currency_{{ $item->currency_id ?: $account->getCurrencyId() }}" style="display:none">
                                    {{ trans('texts.average_invoice') }}
                                    {{ Utils::formatMoney($item->invoice_avg, $item->currency_id) }} |
                                </span>
                            @endforeach
                            <span class="average-div" style="color:#337ab7"/>
                        </div>
                    @endif
                    <i class="glyphicon glyphicon-ok-sign"></i> {{ trans('texts.recent_payments') }}
                </h3>
            </div>
            <div class="panel-body" style="height:274px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.payment_date') }}</th>
                        <th>{{ trans('texts.amount') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                        <tr>
                            <td>{!! \App\Models\Invoice::calcLink($payment) !!}</td>
                            @can('viewByOwner', [ENTITY_CLIENT, $payment->client_user_id])
                                <td>{!! link_to('/clients/'.$payment->client_public_id, trim($payment->client_name) ?: (trim($payment->first_name . ' ' . $payment->last_name) ?: $payment->email)) !!}</td>
                            @else
                                <td>{{ trim($payment->client_name) ?: (trim($payment->first_name . ' ' . $payment->last_name) ?: $payment->email) }}</td>
                            @endcan
                            <td>{{ Utils::fromSqlDate($payment->payment_date) }}</td>
                            <td>{{ Utils::formatMoney($payment->amount, $payment->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default dashboard" style="height:320px;">
            <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">
                <h3 class="panel-title" style="color: black !important">
                    <i class="glyphicon glyphicon-time"></i> {{ trans('texts.upcoming_invoices') }}
                </h3>
            </div>
            <div class="panel-body" style="height:274px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.due_date') }}</th>
                        <th>{{ trans('texts.balance_due') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($upcoming as $invoice)
                            @if ($invoice->invoice_type_id == INVOICE_TYPE_STANDARD)
                                <tr>
                                    <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>
                                    @can('viewByOwner', [ENTITY_CLIENT, $invoice->client_user_id])
                                        <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>
                                    @else
                                        <td>{{ trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email) }}</td>
                                    @endcan
                                    <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                                    <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default dashboard" style="height:320px">
            <div class="panel-heading" style="background-color:#777 !important">
                <h3 class="panel-title in-bold-white">
                    <i class="glyphicon glyphicon-time"></i> {{ trans('texts.invoices_past_due') }}
                </h3>
            </div>
            <div class="panel-body" style="height:274px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.due_date') }}</th>
                        <th>{{ trans('texts.balance_due') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($pastDue as $invoice)
                            @if ($invoice->invoice_type_id == INVOICE_TYPE_STANDARD)
                                <tr>
                                    <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>
                                    @can('viewByOwner', [ENTITY_CLIENT, $invoice->client_user_id])
                                        <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>
                                    @else
                                        <td>{{ trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email) }}</td>
                                    @endcan
                                    <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                                    <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($hasQuotes)
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default dashboard" style="height:320px;">
                <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">
                    <h3 class="panel-title" style="color: black !important">
                        <i class="glyphicon glyphicon-time"></i> {{ trans('texts.upcoming_quotes') }}
                    </h3>
                </div>
                <div class="panel-body" style="height:274px;overflow-y:auto;">
                    <table class="table table-striped">
                        <thead>
                            <th>{{ trans('texts.quote_number_short') }}</th>
                            <th>{{ trans('texts.client') }}</th>
                            <th>{{ trans('texts.valid_until') }}</th>
                            <th>{{ trans('texts.amount') }}</th>
                        </thead>
                        <tbody>
                            @foreach ($upcoming as $invoice)
                                @if ($invoice->invoice_type_id == INVOICE_TYPE_QUOTE)
                                    <tr>
                                        <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>
                                        <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>
                                        <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                                        <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default dashboard" style="height:320px">
                <div class="panel-heading" style="background-color:#777 !important">
                    <h3 class="panel-title in-bold-white">
                        <i class="glyphicon glyphicon-time"></i> {{ trans('texts.expired_quotes') }}
                    </h3>
                </div>
                <div class="panel-body" style="height:274px;overflow-y:auto;">
                    <table class="table table-striped">
                        <thead>
                            <th>{{ trans('texts.quote_number_short') }}</th>
                            <th>{{ trans('texts.client') }}</th>
                            <th>{{ trans('texts.valid_until') }}</th>
                            <th>{{ trans('texts.amount') }}</th>
                        </thead>
                        <tbody>
                            @foreach ($pastDue as $invoice)
                                @if ($invoice->invoice_type_id == INVOICE_TYPE_QUOTE)
                                    <tr>
                                        <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>
                                        <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>
                                        <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                                        <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif

<script type="text/javascript">
    $(function() {
        $('.normalDropDown:not(.dropdown-toggle)').click(function() {
            window.location = '{{ URL::to('invoices/create') }}';
        });
    });
</script>

@stop
