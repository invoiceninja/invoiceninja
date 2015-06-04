@extends('header')

@section('content')

    <style type="text/css">

    .date-group div.input-group {
        width: 250px;
    }

    .time-input input,
    .time-input select {
        float: left;
        width: 110px;
    }
    </style>


    {!! Former::open($url)->addClass('col-md-10 col-md-offset-1 warn-on-exit task-form')->method($method)->rules(array(

    )) !!}

    @if ($task)
        {!! Former::populate($task) !!}
    @endif

    <div style="display:none">
        {!! Former::text('action') !!}
        {!! Former::text('start_time') !!}
        {!! Former::text('duration') !!}
    </div>
    
    <div class="row">
        <div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

            {!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
            {!! Former::textarea('description')->rows(3) !!}

            @if ($task && $task->duration == -1)
                <center>                    
                    <div id="duration-text" style="font-size: 36px; font-weight: 300; padding: 30px 0 20px 0"/>
                </center>
            @else
                @if (!$task)
                    {!! Former::radios('task_type')->radios([
                            trans('texts.timer') => array('name' => 'task_type', 'value' => 'timer'),
                            trans('texts.manual') => array('name' => 'task_type', 'value' => 'manual'),
                    ])->inline()->check('timer')->label('&nbsp;') !!}
                    <div id="datetime-details" style="display: none">
                    <br>
                @else
                    <div>
                @endif
                    {!! Former::text('date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                            ->append('<i class="glyphicon glyphicon-calendar"></i>')->addGroupClass('date-group time-input') !!}

                    <div class="form-group">
                        <label for="time" class="control-label col-lg-4 col-sm-4">
                            {{ trans('texts.time') }}
                        </label>
                        <div class="col-lg-8 col-sm-8 time-input">
                            <input class="form-control" id="start_hours" placeholder="{{ uctrans('texts.hours') }}" 
                                name="value" size="3" type="number" min="1" max="12" step="1"/>
                            <input class="form-control" id="start_minutes" placeholder="{{ uctrans('texts.minutes') }}" 
                                name="value" size="2" type="number" min="0" max="59" step="1"/>
                            <input class="form-control" id="start_seconds" placeholder="{{ uctrans('texts.seconds') }}" 
                                name="value" size="2" type="number" min="0" max="59" step="1"/>                            
                            <select class="form-control" id="start_ampm">
                                <option>AM</option>
                                <option>PM</option>
                            </select>
                        </div>
                    </div>                    

                    <div class="form-group">
                        <label class="control-label col-lg-4 col-sm-4">
                            {{ trans('texts.duration') }}
                        </label>
                        <div class="col-lg-8 col-sm-8 time-input">
                            <input class="form-control" id="duration_hours" placeholder="{{ uctrans('texts.hours') }}" 
                                name="value" size="3" type="number" min="0" step="1"/>
                            <input class="form-control" id="duration_minutes" placeholder="{{ uctrans('texts.minutes') }}" 
                                name="value" size="2" type="number" min="0" max="59" step="1"/>
                            <input class="form-control" id="duration_seconds" placeholder="{{ uctrans('texts.seconds') }}" 
                                name="value" size="2" type="number" min="0" max="59" step="1"/>
                        </div>
                    </div>                    

                    <div class="form-group end-time">
                        <label for="end-time" class="control-label col-lg-4 col-sm-4">
                            {{ trans('texts.end') }}
                        </label>
                        <div class="col-lg-8 col-sm-8" style="padding-top: 10px">
                        </div>
                    </div>

                </div>
            @endif

            </div>
            </div>

        </div>
    </div>


    <center class="buttons">
        @if ($task && $task->duration == -1)
            {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button']) !!}            
            {!! Button::primary(trans('texts.stop'))->large()->appendIcon(Icon::create('stop'))->withAttributes(['id' => 'stop-button']) !!}            
        @else
            {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/tasks'))->appendIcon(Icon::create('remove-circle')) !!}
            @if ($task)
                {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button']) !!}
            @else
                {!! Button::success(trans('texts.start'))->large()->appendIcon(Icon::create('play'))->withAttributes(['id' => 'start-button']) !!}
                {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button', 'style' => 'display:none']) !!}
            @endif
        @endif
    </center>

    {!! Former::close() !!}

    <script type="text/javascript">

    
    var clients = {!! $clients !!};
    
    function tock() {
        var timeLabels = {};
        @foreach (['hour', 'minute', 'second'] as $period)
            timeLabels['{{ $period }}'] = '{{ trans("texts.{$period}") }}';
            timeLabels['{{ $period }}s'] = '{{ trans("texts.{$period}s") }}';
        @endforeach

        var now = Math.floor(Date.now() / 1000);
        var duration = secondsToTime(now - NINJA.startTime); 
        var data = [];
        var periods = ['hour', 'minute', 'second'];

        for (var i=0; i<periods.length; i++) {
            var period = periods[i];
            var letter = period.charAt(0);
            var value = duration[letter];            
            if (!value && !data.length) {
                continue;
            }
            period = value == 1 ? timeLabels[period] : timeLabels[period + 's'];
            data.push(value + ' ' + period);
        }

        $('#duration-text').html(data.length ? data.join(', ') : '0 ' + timeLabels['seconds']);

        setTimeout(function() {
            tock();
        }, 1000);
    }

    function determineEndTime() {        
        var startDate = moment($('#date').datepicker('getDate'));
        var parts = [$('#start_hours').val(), $('#start_minutes').val(), $('#start_seconds').val(), $('#start_ampm').val()];
        var date = moment(startDate.format('YYYY-MM-DD') + ' ' + parts.join(':'), 'YYYY-MM-DD h:m:s:a', true);
        var duration = (parseInt($('#duration_seconds').val(), 10) || 0) 
                        + (60 * (parseInt($('#duration_minutes').val(), 10) || 0))
                        + (60 * 60 * (parseInt($('#duration_hours').val(), 10)) || 0);        

        $('#start_time').val(date.utc().format("YYYY-MM-DD HH:mm:ss"));
        $('#duration').val(duration);

        date.add(duration, 's')
        $('div.end-time div').html(date.local().calendar());        
    }

    function submitAction(action) {
        $('#action').val(action);
        $('.task-form').submit();
    }

    $(function() {

        var $clientSelect = $('select#client');     
        for (var i=0; i<clients.length; i++) {
            var client = clients[i];
            $clientSelect.append(new Option(getClientDisplayName(client), client.public_id));
        }        

        if ({{ $clientPublicId ? 'true' : 'false' }}) {
            $clientSelect.val({{ $clientPublicId }});
        }

        $clientSelect.combobox();
     
        @if ($task)   
            $('#date').datepicker('update', new Date('{{ Utils::fromSqlDateTime($task->start_time) }}'));
        @else
            var date = new Date();
            $('#date').datepicker('update', date);
            $('#start_hours').val((date.getHours() % 12) || 12);
            $('#start_minutes').val(date.getMinutes());
            $('#start_seconds').val(date.getSeconds());
            $('#start_ampm').val(date.getHours() >= 12 ? 'PM' : 'AM');
        @endif

        @if (!$task && !$clientPublicId)
            $('.client-select input.form-control').focus();
        @else
            $('#amount').focus();
        @endif

        $('input[type=radio').change(function(event) {
            var val = $(event.target).val();
            if (val == 'timer') {
                $('#datetime-details').hide();
            } else {
                $('#datetime-details').fadeIn();        
            }
                $('#start-button').toggle();
                $('#save-button').toggle();
        })

        $('#start-button').click(function() {
            submitAction('start');
        });
        $('#save-button').click(function() {
            submitAction('save');
        });
        $('#stop-button').click(function() {
            submitAction('stop');
        });

        $('.time-input').on('keyup change', (function() {
            determineEndTime();
        }));

        @if ($task)
            NINJA.startTime = {{ strtotime($task->start_time) }};            
            @if ($task->duration == -1)
                tock();
            @else
                var date = new Date(NINJA.startTime * 1000);
                var hours = date.getHours();
                var pm = false;
                if (hours >= 12) {
                    pm = true;
                    if (hours > 12) {
                        hours -= 12;
                    }
                }
                if (!hours) {
                    hours = 12;                    
                }

                $('#start_hours').val(hours);
                $('#start_minutes').val(twoDigits(date.getMinutes()));
                $('#start_seconds').val(twoDigits(date.getSeconds()));
                $('#start_ampm').val(pm ? 'PM' : 'AM');
                
                var parts = secondsToTime({{ $task->duration }});
                $('#duration_hours').val(parts['h']);
                $('#duration_minutes').val(parts['m']);
                $('#duration_seconds').val(parts['s']);            
            @endif
        @endif

        determineEndTime();    
    });    

    </script>

@stop