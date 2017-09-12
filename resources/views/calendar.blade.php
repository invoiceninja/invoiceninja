@extends('header')

@section('head')
	@parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>

    <script src="{{ asset('js/fullcalendar.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/fullcalendar.css') }}" rel="stylesheet" type="text/css"/>

@stop

@section('top-right')
    <select class="form-control" style="width: 220px" id="entityTypeFilter" multiple="true">
        @foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, ENTITY_TASK, ENTITY_EXPENSE] as $value)
            <option value="{{ $value }}">{{ trans("texts.{$value}") }}</option>
        @endforeach
    </select>
@stop

@section('content')

    <div id='calendar'></div>

    <script type="text/javascript">

        $(function() {

            // Setup state/status filter
    		$('#entityTypeFilter').select2({
    			placeholder: "{{ trans('texts.filter') }}",
                /*
    			templateSelection: function(data, container) {
    				if (data.id == 'archived') {
    					$(container).css('color', '#fff');
    					$(container).css('background-color', '#f0ad4e');
    					$(container).css('border-color', '#eea236');
    				}
    				return data.text;
    			}
                */
    		}).on('change', function() {
                /*
    			var filter = $('#statuses').val();
    			if (filter) {
    				filter = filter.join(',');
    			} else {
    				filter = '';
    			}
                */
    		}).maximizeSelect2Height();


            $('#calendar').fullCalendar({
                header: {
    				left: 'prev,next today',
    				center: 'title',
    				right: 'month,basicWeek,basicDay,listWeek'
    			},
                defaultDate: '{{ date('Y-m-d') }}',
    			//navLinks: true,
    			//editable: true,
    			eventLimit: true,
                events: {
                    url: '{{ url('/calendar_events') }}',
                    type: 'GET',
                    data: {
                        custom_param1: 'something',
                        custom_param2: 'somethingelse'
                    },
                    error: function() {
                        alert('there was an error while fetching events!');
                    },
                    color: 'red',   // a non-ajax option
                    textColor: 'white' // a non-ajax option
                }
            });
        });

    </script>

@stop
