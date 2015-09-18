@extends('header')

@section('content')

    <style type="text/css">

    input.time-input {
        width: 110px;
        font-size: 14px !important;
    }
    </style>


    {!! Former::open($url)->addClass('col-md-10 col-md-offset-1 warn-on-exit task-form')->method($method)->rules(array()) !!}
    @if ($task)
        {!! Former::populate($task) !!}
        {!! Former::populateField('id', $task->public_id) !!}
    @endif

    <div style="display:none">
        @if ($task)
            {!! Former::text('id') !!}
            {!! Former::text('invoice_id') !!}
        @endif
        {!! Former::text('action') !!}
        {!! Former::text('time_log') !!}
    </div>
    
    <div class="row">
        <div class="col-md-12">

            <div class="panel panel-default">
            <div class="panel-body">

            {!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
            {!! Former::textarea('description')->rows(3) !!}

            @if ($task)

                <div class="form-group simple-time" id="editDetailsLink">
                    <label for="simple-time" class="control-label col-lg-4 col-sm-4">  
                    </label>
                    <div class="col-lg-8 col-sm-8" style="padding-top: 10px">
                        <p>{{ $task->getStartTime() }} - 
                        @if (Auth::user()->account->timezone_id)
                            {{ $timezone }}
                        @else
                            {!! link_to('/company/details?focus=timezone_id', $timezone, ['target' => '_blank']) !!}
                        @endif
                        <p/>

                        @if ($task->hasPreviousDuration())
                            {{ trans('texts.duration') . ': ' . gmdate('H:i:s', $task->getDuration()) }}<br/>
                        @endif

                        @if (!$task->is_running)
                            <p>{!! Button::primary(trans('texts.edit_details'))->withAttributes(['onclick'=>'showTimeDetails()'])->small() !!}</p>
                        @endif
                    </div>
                </div>

                @if ($task->is_running)
                    <center>                    
                        <div id="duration-text" style="font-size: 36px; font-weight: 300; padding: 30px 0 20px 0"/>
                    </center>
                @endif

            @else
                {!! Former::radios('task_type')->radios([
                        trans('texts.timer') => array('name' => 'task_type', 'value' => 'timer'),
                        trans('texts.manual') => array('name' => 'task_type', 'value' => 'manual'),
                ])->inline()->check('timer')->label('&nbsp;') !!}
            @endif

            <div class="form-group simple-time" id="datetime-details" style="display: none">
                <label for="simple-time" class="control-label col-lg-4 col-sm-4">
                    {{ trans('texts.times') }}
                </label>
                <div class="col-lg-8 col-sm-8">

                <table class="table" style="margin-bottom: 0px !important;">
                    <tbody data-bind="foreach: $root.time_log">
                        <tr data-bind="event: { mouseover: showActions, mouseout: hideActions }">
                            <td style="padding: 0px 12px 12px 0 !important">
                                <div data-bind="css: { 'has-error': !isStartValid() }">
                                    <input type="text" data-bind="value: startTime.pretty, event:{ change: $root.refresh }" 
                                        class="form-control time-input" placeholder="{{ trans('texts.start_time') }}"/>
                                </div>
                            </td>
                            <td style="padding: 0px 12px 12px 0 !important">
                                <div data-bind="css: { 'has-error': !isEndValid() }">
                                    <input type="text" data-bind="value: endTime.pretty, event:{ change: $root.refresh }" 
                                        class="form-control time-input" placeholder="{{ trans('texts.end_time') }}"/>
                                </div>
                            </td>
                            <td style="width:100px">                                
                                <div data-bind="text: duration.pretty, visible: !isEmpty()"></div>
                                <a href="#" data-bind="click: function() { setNow(), $root.refresh() }, visible: isEmpty()">{{ trans('texts.set_now') }}</a>
                            </td>                            
                            <td style="width:30px" class="td-icon">
                                <i style="width:12px;cursor:pointer" data-bind="click: $root.removeItem, visible: actionsVisible() &amp;&amp; !isEmpty()" class="fa fa-minus-circle redlink" title="Remove item"/>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            </div>
            </div>

        </div>        
    </div>


    <center class="buttons">
        @if ($task && $task->is_running)
            {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button']) !!}            
            {!! Button::primary(trans('texts.stop'))->large()->appendIcon(Icon::create('stop'))->withAttributes(['id' => 'stop-button']) !!}            
        @else
            {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/tasks'))->appendIcon(Icon::create('remove-circle')) !!}
            @if ($task)
                {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button']) !!}
                {!! Button::primary(trans('texts.resume'))->large()->appendIcon(Icon::create('play'))->withAttributes(['id' => 'resume-button']) !!}
                {!! DropdownButton::normal(trans('texts.more_actions'))
                      ->withContents($actions)
                      ->large()
                      ->dropup() !!}
            @else
                {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button', 'style' => 'display:none']) !!}
                {!! Button::success(trans('texts.start'))->large()->appendIcon(Icon::create('play'))->withAttributes(['id' => 'start-button']) !!}
            @endif
        @endif
    </center>

    {!! Former::close() !!}

    <script type="text/javascript">

    var clients = {!! $clients !!};
    var timeLabels = {};
    @foreach (['hour', 'minute', 'second'] as $period)
        timeLabels['{{ $period }}'] = '{{ trans("texts.{$period}") }}';
        timeLabels['{{ $period }}s'] = '{{ trans("texts.{$period}s") }}';
    @endforeach
    
    function tock(duration) {
        var str = convertDurationToString(duration);
        $('#duration-text').html(str);

        setTimeout(function() {
            tock(duration+1);
        }, 1000);
    }

    function convertDurationToString(duration) {
        var data = [];
        var periods = ['hour', 'minute', 'second'];
        var parts = secondsToTime(duration);

        for (var i=0; i<periods.length; i++) {
            var period = periods[i];
            var letter = period.charAt(0);
            var value = parts[letter];            
            if (!value) {
                continue;
            }
            period = value == 1 ? timeLabels[period] : timeLabels[period + 's'];
            data.push(value + ' ' + period);
        }

        return data.length ? data.join(', ') : '0 ' + timeLabels['seconds'];
    }

    function submitAction(action, invoice_id) {
        model.refresh();
        var data = [];
        for (var i=0; i<model.time_log().length; i++) {
            var timeLog = model.time_log()[i];
            if (!timeLog.isEmpty()) {
                data.push([timeLog.startTime(),timeLog.endTime()]);
            }
            @if ($task && !$task->is_running)
                if (!timeLog.isStartValid() || !timeLog.isEndValid()) {
                    alert("{!! trans('texts.task_errors') !!}");
                    showTimeDetails();
                    return;
                }
            @endif

        }        
        $('#invoice_id').val(invoice_id);
        $('#time_log').val(JSON.stringify(data));
        $('#action').val(action);
        $('.task-form').submit();
    }

    function onDeleteClick() {
        if (confirm('{!! trans("texts.are_you_sure") !!}')) {       
            submitAction('delete');     
        }       
    }

    function showTimeDetails() {
        $('#datetime-details').fadeIn();
        $('#editDetailsLink').hide();
    }

    function TimeModel(data) {
        var self = this;

        var dateTimeFormat = '{{ $datetimeFormat }}';
        var timezone = '{{ $timezone }}';

        self.startTime = ko.observable(0);
        self.endTime = ko.observable(0);
        self.duration = ko.observable(0);
        self.actionsVisible = ko.observable(false);
        self.isStartValid = ko.observable(true);
        self.isEndValid = ko.observable(true);

        if (data) {
            self.startTime(data[0]);
            self.endTime(data[1]);
        };

        self.isEmpty = ko.computed(function() {
            return !self.startTime() && !self.endTime();
        });

        self.startTime.pretty = ko.computed({
            read: function() {                
                return self.startTime() ? moment.unix(self.startTime()).tz(timezone).format(dateTimeFormat) : '';    
            }, 
            write: function(data) {
                self.startTime(moment(data, dateTimeFormat).tz(timezone).unix());
            }
        });

        self.endTime.pretty = ko.computed({
            read: function() {
                return self.endTime() ? moment.unix(self.endTime()).tz(timezone).format(dateTimeFormat) : '';
            }, 
            write: function(data) {
                self.endTime(moment(data, dateTimeFormat).tz(timezone).unix());
            }
        });

        self.setNow = function() {
            self.startTime(moment.tz(timezone).unix());
            self.endTime(moment.tz(timezone).unix());
        }

        self.duration.pretty = ko.computed(function() {
            var duration = false;
            var start = self.startTime();
            var end = self.endTime();

            if (start && end) {
                var duration = end - start;
            }

            var duration = moment.duration(duration * 1000);
            return Math.floor(duration.asHours()) + moment.utc(duration.asMilliseconds()).format(":mm:ss")
        }, self);

        /*
        self.isEmpty = function() {
            return false;
        };
        */        

        self.hideActions = function() {
            self.actionsVisible(false);
        };

        self.showActions = function() {
            self.actionsVisible(true);
        };       
    }

    function ViewModel(data) {
        var self = this;
        self.time_log = ko.observableArray();

        if (data) {
            data = JSON.parse(data.time_log);
            for (var i=0; i<data.length; i++) {
                self.time_log.push(new TimeModel(data[i]));
            }            
        }
        self.time_log.push(new TimeModel());

        self.removeItem = function(item) {            
            self.time_log.remove(item);   
            self.refresh();         
        }

        self.refresh = function() {
            var hasEmpty = false;
            var lastTime = 0;
            self.time_log.sort(function(left, right) {
                if (left.isEmpty() || right.isEmpty()) {
                    return -1;
                }
                return left.startTime() - right.startTime();
            });
            for (var i=0; i<self.time_log().length; i++) {
                var timeLog = self.time_log()[i];
                var startValid = true;
                var endValid = true;
                if (timeLog.isEmpty()) {
                    hasEmpty = true;
                } else {
                    if (timeLog.startTime() < lastTime || timeLog.startTime() > timeLog.endTime()) {
                        startValid = false;
                    }
                    if (timeLog.endTime() < Math.min(timeLog.startTime(), lastTime)) {
                        endValid = false;
                    }
                    lastTime = Math.max(lastTime, timeLog.endTime());                    
                }
                timeLog.isStartValid(startValid);
                timeLog.isEndValid(endValid);
            }
            if (!hasEmpty) {
                self.addItem();
            }
        }

        self.addItem = function() {
            self.time_log.push(new TimeModel());
        }            
    }

    window.model = new ViewModel({!! $task !!});
    ko.applyBindings(model);

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
     
        @if (!$task && !$clientPublicId)
            $('.client-select input.form-control').focus();
        @else
            $('#amount').focus();
        @endif

        $('input[type=radio]').change(function(event) {
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
        $('#resume-button').click(function() {
            submitAction('resume');
        });

        @if ($task)
            @if ($task->is_running)
                tock({{ $duration }});
            @endif
        @endif
    });    

    </script>

@stop
