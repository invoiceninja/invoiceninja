@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/jquery.datetimepicker.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/jquery.datetimepicker.css') }}" rel="stylesheet" type="text/css"/>
@stop

@section('content')

    <style type="text/css">

    input.time-input {
        width: 100%;
        font-size: 14px !important;
    }

    </style>

    @if ($errors->first('time_log'))
        <div class="alert alert-danger"><li>{{ trans('texts.task_errors') }}  </li></div>
    @endif

    {!! Former::open($url)
            ->addClass('col-md-10 col-md-offset-1 warn-on-exit task-form')
            ->onsubmit('return onFormSubmit(event)')
            ->method($method) !!}

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

            @if ($task && $task->invoice_id)
                {!! Former::plaintext()
                        ->label('client')
                        ->value($task->client->present()->link) !!}
                @if ($task->project)
                    {!! Former::plaintext()
                            ->label('project')
                            ->value($task->present()->project) !!}
                @endif
            @else
                {!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
                {!! Former::select('project_id')
                        ->addOption('', '')
                        ->addGroupClass('project-select')
                        ->label(trans('texts.project')) !!}
            @endif

            {!! Former::textarea('description')->rows(4) !!}

            @if ($task)

                <div class="form-group simple-time" id="editDetailsLink">
                    <label for="simple-time" class="control-label col-lg-4 col-sm-4">
                    </label>
                    <div class="col-lg-8 col-sm-8" style="padding-top: 10px">
                        <p>{{ $task->getStartTime() }} -
                        @if (Auth::user()->account->timezone_id)
                            {{ $timezone }}
                        @else
                            {!! link_to('/settings/localization?focus=timezone_id', $timezone, ['target' => '_blank']) !!}
                        @endif
                        <p/>

                        @if ($task->hasPreviousDuration())
                            {{ trans('texts.duration') . ': ' . Utils::formatTime($task->getDuration()) }}<br/>
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
                                    <input type="text" data-bind="dateTimePicker: startTime.pretty, event:{ change: $root.refresh }"
                                        class="form-control time-input time-input-start" placeholder="{{ trans('texts.start_time') }}"/>
                                </div>
                            </td>
                            <td style="padding: 0px 12px 12px 0 !important">
                                <div data-bind="css: { 'has-error': !isEndValid() }">
                                    <input type="text" data-bind="dateTimePicker: endTime.pretty, event:{ change: $root.refresh }"
                                        class="form-control time-input time-input-end" placeholder="{{ trans('texts.end_time') }}"/>
                                </div>
                            </td>
                            <td style="padding: 0px 12px 12px 0 !important; width:100px">
                                <input type="text" data-bind="value: duration.pretty, visible: !isEmpty()" class="form-control"></div>
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

    @if (Auth::user()->canCreateOrEdit(ENTITY_TASK, $task))
        @if (Auth::user()->hasFeature(FEATURE_TASKS))
            @if ($task && $task->is_running)
                {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button']) !!}
                {!! Button::primary(trans('texts.stop'))->large()->appendIcon(Icon::create('stop'))->withAttributes(['id' => 'stop-button']) !!}
            @elseif ($task && $task->is_deleted)
                {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/tasks'))->appendIcon(Icon::create('remove-circle')) !!}
                {!! Button::primary(trans('texts.restore'))->large()->withAttributes(['onclick' => 'submitAction("restore")'])->appendIcon(Icon::create('cloud-download')) !!}
            @elseif ($task && $task->trashed())
                {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/tasks'))->appendIcon(Icon::create('remove-circle')) !!}
                {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button']) !!}
                {!! Button::primary(trans('texts.restore'))->large()->withAttributes(['onclick' => 'submitAction("restore")'])->appendIcon(Icon::create('cloud-download')) !!}
            @else
                {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/tasks'))->appendIcon(Icon::create('remove-circle')) !!}
                @if ($task)
                    {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button']) !!}
                    {!! Button::primary(trans('texts.resume'))->large()->appendIcon(Icon::create('play'))->withAttributes(['id' => 'resume-button']) !!}
                    {!! DropdownButton::normal(trans('texts.more_actions'))
                          ->withContents($actions)
                          ->large()
                          ->dropup() !!}
                @else
                    {!! Button::success(trans('texts.save'))->large()->appendIcon(Icon::create('floppy-disk'))->withAttributes(['id' => 'save-button']) !!}
                    {!! Button::success(trans('texts.start'))->large()->appendIcon(Icon::create('play'))->withAttributes(['id' => 'start-button']) !!}
                @endif
            @endif
        @else
            {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/tasks'))->appendIcon(Icon::create('remove-circle')) !!}
        @endif
    @endif

</center>

    {!! Former::close() !!}

    <script type="text/javascript">

    // Add moment support to the datetimepicker
    Date.parseDate = function( input, format ){
      return moment(input, format).toDate();
    };
    Date.prototype.dateFormat = function( format ){
      return moment(this).format(format);
    };

    ko.bindingHandlers.dateTimePicker = {
      init: function (element, valueAccessor, allBindingsAccessor) {
         var value = ko.utils.unwrapObservable(valueAccessor());
         // http://xdsoft.net/jqplugins/datetimepicker/
         $(element).datetimepicker({
            lang: '{{ $appLanguage }}',
            lazyInit: true,
            validateOnBlur: false,
            step: {{ env('TASK_TIME_STEP', 15) }},
            format: '{{ $datetimeFormat }}',
            formatDate: '{{ $account->getMomentDateFormat() }}',
            formatTime: '{{ $account->military_time ? 'H:mm' : 'h:mm A' }}',
            onSelectTime: function(current_time, $input){
                current_time.setSeconds(0);
                $(element).datetimepicker({
                    value: current_time
                });
                // set end to an hour after the start time
                if ($(element).hasClass('time-input-start')) {
                    var timeModel = ko.dataFor(element);
                    if (!timeModel.endTime()) {
                        timeModel.endTime((current_time.getTime() / 1000));
                    }
                }
            },
            dayOfWeekStart: {{ Session::get('start_of_week') }}
         });

         $(element).change(function() {
            var value = valueAccessor();
            value($(element).val());
         })
      },
      update: function (element, valueAccessor) {
        var value = ko.utils.unwrapObservable(valueAccessor());
        if (value) {
            $(element).val(value);
        }
      }
    }

    var clients = {!! $clients !!};
    var projects = {!! $projects !!};

    var timeLabels = {};
    @foreach (['hour', 'minute', 'second'] as $period)
        timeLabels['{{ $period }}'] = '{{ trans("texts.{$period}") }}';
        timeLabels['{{ $period }}s'] = '{{ trans("texts.{$period}s") }}';
    @endforeach

    function onFormSubmit(event) {
        @if (Auth::user()->canCreateOrEdit(ENTITY_TASK, $task))
            return true;
        @else
            return false
        @endif
    }

    function tock(startTime) {
        var duration = new Date().getTime() - startTime;
        duration = Math.floor(duration / 100) / 10;
        var str = convertDurationToString(duration);
        $('#duration-text').html(str);

        setTimeout(function() {
            tock(startTime);
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

        self.duration.pretty = ko.computed({
            read: function() {
                var duration = false;
                var start = self.startTime();
                var end = self.endTime();

                if (start && end) {
                    var duration = end - start;
                }

                var duration = moment.duration(duration * 1000);
                return Math.floor(duration.asHours()) + moment.utc(duration.asMilliseconds()).format(":mm:ss")
            },
            write: function(data) {
                self.endTime(self.startTime() + convertToSeconds(data));
            }
        });

        /*
        self.duration.pretty = ko.computed(function() {
        }, self);
        */

        self.hideActions = function() {
            self.actionsVisible(false);
        };

        self.showActions = function() {
            self.actionsVisible(true);
        };
    }

    function convertToSeconds(str) {
        if (!str) {
            return 0;
        }
        if (str.indexOf(':') >= 0) {
            return moment.duration(str).asSeconds();
        } else {
            return parseFloat(str) * 60 * 60;
        }
    }

    function loadTimeLog(data) {
        model.time_log.removeAll();
        data = JSON.parse(data);
        for (var i=0; i<data.length; i++) {
            model.time_log.push(new TimeModel(data[i]));
        }
        model.time_log.push(new TimeModel());
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

        self.removeItems = function() {
            self.time_log.removeAll();
            self.refresh();
        }

        self.refresh = function() {
            var hasEmpty = false;
            var lastTime = 0;
            for (var i=0; i<self.time_log().length; i++) {
                var timeLog = self.time_log()[i];
                if (timeLog.isEmpty()) {
                    hasEmpty = true;
                }
            }
            if (!hasEmpty) {
                self.addItem();
            }
        }

        self.showTimeOverlaps = function() {
            var lastTime = 0;
            for (var i=0; i<self.time_log().length; i++) {
                var timeLog = self.time_log()[i];
                var startValid = true;
                var endValid = true;
                if (!timeLog.isEmpty()) {
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
        }

        self.addItem = function() {
            self.time_log.push(new TimeModel());
        }
    }

    window.model = new ViewModel({!! $task !!});
    ko.applyBindings(model);

    function onTaskTypeChange() {
        var val = $('input[name=task_type]:checked').val();
        if (val == 'timer') {
            $('#datetime-details').hide();
        } else {
            $('#datetime-details').fadeIn();
        }
        setButtonsVisible();
        if (isStorageSupported()) {
            localStorage.setItem('last:task_type', val);
        }
    }

    function setButtonsVisible() {
        var val = $('input[name=task_type]:checked').val();
        if (val == 'timer') {
            $('#start-button').show();
            $('#save-button').hide();
        } else {
            $('#start-button').hide();
            $('#save-button').show();
        }
    }

    $(function() {
        $('input[type=radio]').change(function() {
            onTaskTypeChange();
        })

        setButtonsVisible();

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
                tock({{ $task->getLastStartTime() * 1000 }});
            @endif
        @endif

        @if ($errors->first('time_log'))
            loadTimeLog({!! json_encode(Input::old('time_log')) !!});
            model.showTimeOverlaps();
            showTimeDetails();
        @endif

        // setup clients and project comboboxes
        var clientId = {{ $clientPublicId }};
        var projectId = {{ $projectPublicId }};

        var clientMap = {};
        var projectMap = {};
        var projectsForClientMap = {};
        var projectsForAllClients = [];
        var $clientSelect = $('select#client');

        for (var i=0; i<projects.length; i++) {
          var project = projects[i];
          projectMap[project.public_id] = project;

          var client = project.client;
          if (!client) {
              projectsForAllClients.push(project);
          } else {
              if (!projectsForClientMap.hasOwnProperty(client.public_id)) {
                projectsForClientMap[client.public_id] = [];
              }
              projectsForClientMap[client.public_id].push(project);
          }
        }

        for (var i=0; i<clients.length; i++) {
          var client = clients[i];
          clientMap[client.public_id] = client;
        }

        $clientSelect.append(new Option('', ''));
        for (var i=0; i<clients.length; i++) {
          var client = clients[i];
          var clientName = getClientDisplayName(client);
          if (!clientName) {
              continue;
          }
          $clientSelect.append(new Option(clientName, client.public_id));
        }

        if (clientId) {
          $clientSelect.val(clientId);
        }

        $clientSelect.combobox();
        $clientSelect.on('change', function(e) {
          var clientId = $('input[name=client]').val();
          var projectId = $('input[name=project_id]').val();
          var project = projectMap[projectId];
          if (project && ((project.client && project.client.public_id == clientId) || !project.client)) {
            e.preventDefault();return;
          }
          setComboboxValue($('.project-select'), '', '');
          $projectCombobox = $('select#project_id');
          $projectCombobox.find('option').remove().end().combobox('refresh');
          $projectCombobox.append(new Option('', ''));
          @if (Auth::user()->can('create', ENTITY_PROJECT))
            if (clientId) {
                $projectCombobox.append(new Option("{{ trans('texts.create_project')}}: $name", '-1'));
            }
          @endif
          var list = clientId ? (projectsForClientMap.hasOwnProperty(clientId) ? projectsForClientMap[clientId] : []).concat(projectsForAllClients) : projects;
          for (var i=0; i<list.length; i++) {
            var project = list[i];
            $projectCombobox.append(new Option(project.name,  project.public_id));
          }
          $('select#project_id').combobox('refresh');
        });

        var $projectSelect = $('select#project_id').on('change', function(e) {
            $clientCombobox = $('select#client');
            var projectId = $('input[name=project_id]').val();
            if (projectId == '-1') {
                $('input[name=project_name]').val(projectName);
            } else if (projectId) {
                // when selecting a project make sure the client is loaded
                var project = projectMap[projectId];
                if (project && project.client) {
                    var client = clientMap[project.client.public_id];
                    if (client) {
                        project.client = client;
                        setComboboxValue($('.client-select'), client.public_id, getClientDisplayName(client));
                    }
                }
            } else {
                $clientSelect.trigger('change');
            }
        });

        @include('partials/entity_combobox', ['entityType' => ENTITY_PROJECT])

        if (projectId) {
           var project = projectMap[projectId];
           setComboboxValue($('.project-select'), project.public_id, project.name);
           $projectSelect.trigger('change');
        } else {
           $clientSelect.trigger('change');
        }

        @if (!$task)
            var taskType = localStorage.getItem('last:task_type');
            if (taskType) {
                $('input[name=task_type][value='+taskType+']').prop('checked', true);
                onTaskTypeChange();
            }
        @endif

        @if (!$task && !$clientPublicId)
            $('.client-select input.form-control').focus();
        @else
            $('#description').focus();
        @endif
    });

    </script>


@stop
