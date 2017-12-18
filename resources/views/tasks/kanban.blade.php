@extends('header')

@section('head')
    @parent

    <style type="text/css">
        .kanban {
            overflow-x: auto;
            white-space: nowrap;
            min-height: 540px;
        }

        .kanban input {
            width: 100%;
        }

        .kanban-column {
            background-color: #E9E9E9;
            padding: 10px;
            height: 100%;
            width: 230px;
            margin-right: 12px;
            display: inline-block;
            vertical-align: top;
            white-space: normal;
            cursor: pointer;
        }

        .kanban-column-header {
            font-weight: bold;
            padding-bottom: 12px;
        }

        .kanban-column-header .pull-left {
            width: 90%;
        }

        .kanban-column-header .fa-times {
            color: #888888;
            padding-bottom: 6px;
        }

        .kanban-column-header input {
            width: 190px;
        }

        .kanban-column-header .view {
            padding-top: 3px;
            padding-bottom: 3px;
        }

        .kanban-column-row {
            margin-bottom: -12px;
        }

        .kanban-column-row .fa-circle {
            float:right;
            padding-top: 10px;
            padding-right: 8px;
        }

        .kanban-column-row .panel {
            word-break: break-all;
        }

        .kanban-column-row .view div {
            padding: 8px;
        }

        .kanban-column textarea {
            resize: vertical;
            width: 100%;
            padding-left: 8px;
            padding-top: 8px;
        }

        .kanban-column .edit {
            display: none;
        }

        .kanban-column .editing .edit {
            display: block;
        }

        .kanban-column .editing .view {
            display: none;
        }​

        .project-group0 { color: #000000; }
        .project-group1 { color: #1c9f77; }
        .project-group2 { color: #d95d02; }
        .project-group3 { color: #716cb1; }
        .project-group4 { color: #e62a8b; }
        .project-group5 { color: #5fa213; }
        .project-group6 { color: #e6aa04; }
        .project-group7 { color: #a87821; }
        .project-group8 { color: #676767; }

    </style>

@stop

@section('top-right')
    <div class="form-group">
        <input type="text" placeholder="{{ trans('texts.filter') }}" data-bind="value: filter, valueUpdate: 'afterkeydown'"
            class="form-control" style="background-color: #FFFFFF !important"/>
    </div>
@stop

@section('content')

    <script type="text/javascript">

        var statuses = {!! $statuses !!};
        var tasks = {!! $tasks !!};
        var projects = {!! $projects !!};
        var clients = {!! $clients !!};

        var projectMap = {};
        var clientMap = {};
        var statusMap = {};

        ko.bindingHandlers.enterkey = {
            init: function (element, valueAccessor, allBindings, viewModel) {
                var callback = valueAccessor();
                $(element).keypress(function (event) {
                    var keyCode = (event.which ? event.which : event.keyCode);
                    if (keyCode === 13) {
                        callback.call(viewModel);
                        return false;
                    }
                    return true;
                });
            }
        };

        ko.bindingHandlers.selected = {
            update: function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
                var selected = ko.utils.unwrapObservable(valueAccessor());
                if (selected) element.select();
            }
        };

        function ViewModel() {
            var self = this;

            self.statuses = ko.observableArray();
            self.is_adding_status = ko.observable(false);
            self.new_status = ko.observable('');
            self.filter = ko.observable('');
            self.is_sending_request = ko.observable(false);

            for (var i=0; i<statuses.length; i++) {
                var status = statuses[i];
                var statusModel = new StatusModel(status);
                statusMap[status.public_id] = statusModel;
                self.statuses.push(statusModel);
            }

            for (var i=0; i<projects.length; i++) {
                var project = projects[i];
                projectMap[project.public_id] = new ProjectModel(project);
            }

            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                clientMap[client.public_id] = new ClientModel(client);
            }

            for (var i=0; i<tasks.length; i++) {
                var task = tasks[i];
                var taskModel = new TaskModel(task);
                if (task.task_status) {
                    var statusModel = statusMap[task.task_status.public_id];
                } else {
                    var statusModel = self.statuses()[0];
                }
                statusModel.tasks.push(taskModel);
            }

            self.startAddStatus = function() {
                self.is_adding_status(true);
                $('.kanban-column-last .kanban-column-row.editing textarea').focus();
            }

            self.cancelAddStatus = function() {
                self.is_adding_status(false);
            }

            self.completeAddStatus = function() {
                self.is_adding_status(false);
                var statusModel = new StatusModel({
                    name: self.new_status()
                })
                self.statuses.push(statusModel);
            }

            self.onDragged = function() {

            }
        }

        function StatusModel(data) {
            var self = this;
            self.name = ko.observable();
            self.public_id = ko.observable();
            self.is_editing_status = ko.observable(false);
            self.is_header_hovered = ko.observable(false);
            self.tasks = ko.observableArray();
            self.new_task = new TaskModel();

            self.onHeaderMouseOver = function() {
                self.is_header_hovered(true);
            }

            self.onHeaderMouseOut = function() {
                self.is_header_hovered(false);
            }

            /*
            self.inputValue = ko.computed({
                read: function () {
                    return self.is_blank() ? '' : self.name();
                },
                write: function(value) {
                    self.name(value);
                    if (self.is_blank()) {
                        self.is_blank(false);
                        model.statuses.push(new StatusModel());
                    }
                }
            });
            */

            self.startStatusEdit = function() {
                self.is_editing_status(true);
            }

            self.endStatusEdit = function() {
                self.is_editing_status(false);
            }

            self.onDragged = function() {

            }

            self.archiveStatus = function() {
                sweetConfirm(function() {
                    window.model.statuses.remove(self);
                }, "{{ trans('texts.archive_status')}}");
            }

            self.cancelNewTask = function() {
                if (self.new_task.is_blank()) {
                    self.new_task.description('');
                }
                self.new_task.endTaskEdit();
            }

            self.saveNewTask = function() {
                var task = self.new_task;
                var description = (task.description() || '').trim();
                if (! description) {
                    return false;
                }
                var task = new TaskModel({
                    description: description,
                    task_status_id: self.public_id(),
                    task_status_sort_order: self.tasks.length,
                })

                $.ajax({
                    dataType: 'json',
                    type: 'post',
                    data: task.toData(),
                    url: '{{ url('/tasks') }}',
                    accepts: {
                        json: 'application/json'
                    },
                    success: function(response) {
                        task.public_id(response.public_id);
                    },
                    error: function(error) {
                        console.log('error');
                        console.log(error);
                    },
                }).always(function() {
                    setTimeout(function() {
                        model.is_sending_request(false);
                    }, 1000);
                });

                self.tasks.push(task);
                self.new_task.reset();
                self.endStatusEdit();
            }

            if (data) {
                ko.mapping.fromJS(data, {}, this);
            }
        }

        function TaskModel(data) {
            var self = this;
            self.public_id = ko.observable(0);
            self.description = ko.observable('');
            self.description.orig = ko.observable('');
            self.is_blank = ko.observable(false);
            self.is_editing_task = ko.observable(false);
            self.project = ko.observable();
            self.client = ko.observable();
            self.task_status_id = ko.observable();
            self.task_status_sort_order = ko.observable();

            self.projectColor = ko.computed(function() {
                if (! self.project()) {
                    return '';
                }
                var projectId = self.project().public_id();
                var colorNum = (projectId-1) % 8;
                return 'project-group' + (colorNum+1);
            })

            self.startTaskEdit = function() {
                self.description.orig(self.description());
                self.is_editing_task(true);
                $('.kanban-column-row.editing textarea').focus();
            }

            self.endTaskEdit = function() {
                var description = self.description();
                if (! description && ! self.is_blank()) {
                    return false;
                }
                self.is_editing_task(false);
            }

            self.onDragged = function() {

            }

            self.toData = function() {
                return 'description=' + encodeURIComponent(self.description()) +
                    '&task_status_id=' + self.task_status_id() +
                    '&task_status_sort_order=' + self.task_status_sort_order();
            }

            self.matchesFilter = function(filter) {
                if (filter) {
                    filter = filter.toLowerCase();
                    var parts = filter.split(' ');
                    for (var i=0; i<parts.length; i++) {
                        var part = parts[i];
                        var isMatch = false;
                        if (self.description()) {
                            if (self.description().toLowerCase().indexOf(part) >= 0) {
                                isMatch = true;
                            }
                        }
                        if (self.project()) {
                            var projectName = self.project().name();
                            if (projectName && projectName.toLowerCase().indexOf(part) >= 0) {
                                isMatch = true;
                            }
                        }
                        if (self.client()) {
                            var clientName = self.client().displayName();
                            if (clientName && clientName.toLowerCase().indexOf(part) >= 0) {
                                isMatch = true;
                            }
                        }
                        if (! isMatch) {
                            return false;
                        }
                    }
                }

                return true;
            }

            self.cancelEditTask = function() {
                if (self.is_blank()) {
                    self.description('');
                } else {
                    self.description(self.description.orig());
                }

                self.endTaskEdit();
            }

            self.saveEditTask = function() {
                var description = (self.description() || '').trim();
                if (! description) {
                    return false;
                }
                $.ajax({
                    dataType: 'json',
                    type: 'put',
                    data: self.toData(),
                    url: '{{ url('/tasks') }}/' + self.public_id(),
                    accepts: {
                        json: 'application/json'
                    },
                    success: function(response) {
                        console.log('success');
                        console.log(response);
                    },
                    error: function(error) {
                        console.log('error');
                        console.log(error);
                    },
                }).always(function() {
                    setTimeout(function() {
                        model.is_sending_request(false);
                    }, 1000);
                });

                self.endTaskEdit();
            }

            self.viewTask = function() {
                //console.log();
                window.open('{{ url('/tasks') }}/' + self.public_id() + '/edit', 'task');
            }

            self.reset = function() {
                self.endTaskEdit();
                self.description('');
                self.is_blank(true);
            }

            self.mapping = {
                'project': {
                    create: function(options) {
                        return projectMap[options.data.public_id];
                    }
                },
                'client': {
                    create: function(options) {
                        return clientMap[options.data.public_id];
                    }
                }
            }

            if (data) {
                ko.mapping.fromJS(data, self.mapping, this);
            } else {
                //self.description('{{ trans('texts.add_task') }}...');
                self.is_blank(true);
            }
        }

        function ProjectModel(data) {
            var self = this;
            self.name = ko.observable();

            if (data) {
                ko.mapping.fromJS(data, {}, this);
            }
        }

        function ClientModel(data) {
            var self = this;
            self.name = ko.observable();

            self.displayName = ko.computed(function() {
                return self.name();
            })

            if (data) {
                ko.mapping.fromJS(data, {}, this);
            }
        }

        $(function() {
            toastr.options.timeOut = 3000;
            toastr.options.positionClass = 'toast-bottom-right';

            window.model = new ViewModel();
            ko.applyBindings(model);

            $('.kanban').show();
        });

    </script>

    <!-- <div data-bind="text: ko.toJSON(model)"></div> -->
    <script id="itemTmpl" type="text/html">

    </script>


    <div class="kanban" style="display: none">
        <div data-bind="sortable: { data: statuses, as: 'status', afterMove: onDragged, allowDrop: true, connectClass: 'connect-column' }" style="float:left">
            <div class="well kanban-column">

                <div class="kanban-column-header" data-bind="css: { editing: is_editing_status }, event: { mouseover: onHeaderMouseOver, mouseout: onHeaderMouseOut }">
                    <div class="pull-left" data-bind="event: { click: startStatusEdit }">
                        <div class="view" data-bind="text: name"></div>
                        <input class="edit" type="text" data-bind="value: name, hasfocus: is_editing_status, selected: is_editing_status,
                                event: { blur: endStatusEdit }, enterkey: endStatusEdit"/>
                    </div>
                    <div class="pull-right" data-bind="click: archiveStatus, visible: is_header_hovered">
                        <i class="fa fa-times" title="{{ trans('texts.archive') }}"></i>
                    </div><br/>
                </div>

                <div data-bind="sortable: { data: tasks, as: 'task', afterMove: onDragged, allowDrop: true, connectClass: 'connect-row' }" style="min-height:8px">
                    <div class="kanban-column-row" data-bind="css: { editing: is_editing_task }, visible: task.matchesFilter($root.filter())">
                        <div data-bind="event: { click: startTaskEdit }">
                            <div class="view panel">
                                <i class="fa fa-circle" data-bind="visible: project, css: projectColor"></i>
                                <div data-bind="text: description"></div>
                            </div>
                        </div>
                        <div class="edit">
                            <textarea data-bind="value: description, valueUpdate: 'afterkeydown', enterkey: saveEditTask"></textarea>
                            <div class="pull-right">
                                <button type='button' class='btn btn-default btn-sm' data-bind="click: cancelEditTask">
                                    {{ trans('texts.cancel') }}
                                </button>
                                <button type='button' class='btn btn-primary btn-sm' data-bind="click: viewTask">
                                    {{ trans('texts.view') }}
                                </button>
                                <button type='button' class='btn btn-success btn-sm' data-bind="click: saveEditTask">
                                    {{ trans('texts.save') }}
                                </button>
                            </div>
                            <div class="clearfix" style="padding-bottom:20px"></div>
                        </div>
                    </div>
                </div>

                <div class="kanban-column-row" data-bind="css: { editing: new_task.is_editing_task }, with: new_task">
                    <div data-bind="event: { click: startTaskEdit }" style="padding-bottom:6px">
                        <a href="#" class="view text-muted" style="font-size:13px" data-bind="visible: is_blank">
                            {{ trans('texts.new_task') }}...
                        </a>
                    </div>
                    <div class="edit">
                        <textarea data-bind="value: description, valueUpdate: 'afterkeydown', enterkey: $parent.saveNewTask"></textarea>
                        <div class="pull-right">
                            <button type='button' class='btn btn-default btn-sm' data-bind="click: $parent.cancelNewTask">
                                {{ trans('texts.cancel') }}
                            </button>
                            <button type='button' class='btn btn-success btn-sm' data-bind="click: $parent.saveNewTask">
                                {{ trans('texts.save') }}
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="kanban-column kanban-column-last well">
            <div class="kanban-column-row" data-bind="css: { editing: is_adding_status }">
                <div class="view" data-bind="event: { click: startAddStatus }" style="padding-bottom: 8px;">
                    <a href="#" class="text-muted" style="font-size:13px">
                        {{ trans('texts.new_status') }}...
                    </a>
                </div>
                <div class="edit">
                    <input data-bind="value: new_status, valueUpdate: 'afterkeydown',
                        hasfocus: is_adding_status, selected: is_adding_status, enterkey: completeAddStatus"></textarea>
                    <div class="pull-right" style="padding-top:6px">
                        <button type='button' class='btn btn-default btn-sm' data-bind="click: cancelAddStatus">
                            {{ trans('texts.cancel') }}
                        </button>
                        <button type='button' class='btn btn-success btn-sm' data-bind="click: completeAddStatus">
                            {{ trans('texts.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

@stop
