@extends('header')

@section('head')
	@parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>

    <script src="{{ asset('js/fullcalendar.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/fullcalendar.css') }}" rel="stylesheet" type="text/css"/>

@stop

@section('head_css')
	@parent

	<style type="text/css">
		.fc-day,
		.fc-list-item {
			background-color: white;
		}
	</style>
@stop


@section('top-right')
    <select class="form-control" style="width: 220px" id="entityTypeFilter" multiple="true">
        @foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, ENTITY_TASK, ENTITY_EXPENSE] as $value)
            <option value="{{ $value }}">{{ trans("texts.{$value}") }}</option>
        @endforeach
    </select>
@stop

@section('content')

	@if (!Utils::isPro())
		<div class="alert alert-warning" style="font-size:larger">
			<center>
				{!! trans('texts.pro_plan_calendar', ['link'=>'<a href="javascript:showUpgradeModal()">' . trans('texts.pro_plan_remove_logo_link') . '</a>']) !!}
			</center>
		</div>
	@endif

    <div id='calendar'></div>

    <script type="text/javascript">

        $(function() {

			var lastFilter = false;
			if (isStorageSupported()) {
				lastFilter = JSON.parse(localStorage.getItem('last:calendar_filter'));
			}

            // Setup state/status filter
    		$('#entityTypeFilter').select2({
    			placeholder: "{{ trans('texts.filter') }}",
    		}).val(lastFilter).trigger('change').on('change', function() {
				$('#calendar').fullCalendar('refetchEvents');
				if (isStorageSupported()) {
					var filter = JSON.stringify($('#entityTypeFilter').val());
					localStorage.setItem('last:calendar_filter', filter);
				}
    		}).maximizeSelect2Height();

            $('#calendar').fullCalendar({
				locale: '{{ App::getLocale() }}',
				firstDay: {{ $account->start_of_week ?: '0' }},
                header: {
    				left: 'prev,next today',
    				center: 'title',
    				right: 'month,basicWeek,basicDay,listWeek'
    			},
                defaultDate: '{{ date('Y-m-d') }}',
    			eventLimit: true,
                events: {
                    url: '{{ url('/calendar_events') }}',
                    type: 'GET',
					data: function() {
			            return {
			                filter: $('#entityTypeFilter').val()
			            };
			        },
                    error: function() {
                        alert("{{ trans('texts.error_refresh_page') }}");
                    },
                }
            });
        });

    </script>

@stop
