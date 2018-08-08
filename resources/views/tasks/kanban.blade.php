@extends('header')

@section('head')
    @parent

    <style type="text/css">

        .kanban {
            overflow-x: auto;
            white-space: nowrap;
            min-height: 400px;
        }

        .kanban input {
            width: 100%;
        }

        .tt-input {
            background-color: #FFFFFF !important;
        }

        .kanban-column {
            background-color: #E9E9E9;
            padding: 10px;
            padding-bottom: 14px;
            height: 100%;
            width: 230px;
            margin-right: 12px;
            display: inline-block;
            vertical-align: top;
            white-space: normal;
            cursor: pointer;
        }

        .kanban-column-last {
            background-color: #F8F8F8;
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
            padding-top: 4px !important;
            padding-bottom: 4px !important;
        }

        .kanban-column-row .panel.hovered {
            background-color: #FDFDFD;
        }

        .kanban-column-row div {
            min-height: 36px;
        }

        .kanban-column-row .running div {
            border: 2px groove #36c157;
            border-radius: 4px;
        }

        .kanban-column-row .view div {
            padding: 8px;
        }

        .kanban-column textarea {
            resize: vertical;
            width: 100%;
            padding-left: 8px;
            padding-top: 12px;
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
        <input type="text" data-bind="value: filter" placeholder="{{ trans('texts.filter') }}" id="filter"
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

        var clientList = [];
        var projectList = [];

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

        ko.bindingHandlers.escapekey = {
            init: function (element, valueAccessor, allBindings, viewModel) {
                var callback = valueAccessor();
                $(element).keyup(function (event) {
                    var keyCode = (event.which ? event.which : event.keyCode);
                    if (keyCode === 27) {
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
            self.new_status = ko.observable();
            self.filter = ko.observable();
            self.filter_client_id = ko.observable();
            self.filter_project_id = ko.observable();
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
                projectList.push({
                    type: 'project',
                    value: project.name,
                    tokens: project.name,
                    id: project.public_id,
                })
            }

            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                clientMap[client.public_id] = new ClientModel(client);
                var tokens = [client.name];
                if (client.contacts.length) {
                    $.each(client.contacts, function(i, contact) {
                        tokens.push(contact.first_name, contact.last_name, contact.email);
                    });
                }
                clientList.push({
                    type: 'client',
                    value: getClientDisplayName(client),
                    tokens: tokens,
                    id: client.public_id,
                })
            }

            for (var i=0; i<tasks.length; i++) {
                var task = tasks[i];
                var taskModel = new TaskModel(task);
                var statusModel = false;
                if (task.task_status) {
                    var statusModel = statusMap[task.task_status.public_id];
                }
                if (! statusModel) {
                    statusModel = self.statuses()[0];
                }
                if (statusModel) {
                    statusModel.tasks.push(taskModel);
                }
            }

            self.startNewStatus = function() {
                self.is_adding_status(true);
                $('#newStatusName').val('').focus();
            }

            self.cancelNewStatus = function() {
                self.is_adding_status(false);
            }

            self.saveNewStatus = function() {
                var statusModel = new StatusModel({
                    name: self.new_status(),
                    sort_order: self.statuses().length,
                })
                var url = '{{ url('/task_statuses') }}';
                var data = statusModel.toData();
                self.ajax('post', url, data, function(response) {
                    statusModel.public_id(response.public_id);
                    self.statuses.push(statusModel);
                    self.is_adding_status(false);
                })
            }

            self.ajax = function(method, url, data, callback) {
                // prevent more than one request per second
                if (model.is_sending_request()) {
                    setTimeout(function() {
                        self.ajax(method, url, data, callback);
                    }, 1500);
                    return;
                }
                model.is_sending_request(true);
                $.ajax({
                    type: method,
                    url: url,
                    data: data,
                    dataType: 'json',
                    accepts: {
                        json: 'application/json'
                    },
                    success: function(response) {
                        callback(response);
                    },
                    error: function(error) {
                        swal("{{ trans('texts.error_refresh_page') }}");
                    },
                }).always(function() {
                    setTimeout(function() {
                        model.is_sending_request(false);
                    }, 1500);
                });
            }

            self.onStatusDragged = function(dragged) {
                var status = dragged.item;
                status.sort_order(dragged.targetIndex);

                var url = '{{ url('/task_statuses') }}/' + status.public_id();
                var data = status.toData();

                model.ajax('put', url, data, function(response) {
                    // do nothing
                });
            }
        }

        function StatusModel(data) {
            var self = this;
            self.name = ko.observable();
            self.name.orig = ko.observable();
            self.sort_order = ko.observable();
            self.public_id = ko.observable();
            self.is_editing_status = ko.observable(false);
            self.is_header_hovered = ko.observable(false);
            self.tasks = ko.observableArray();
            self.new_task = new TaskModel();

            self.toData = function() {
                return 'name=' + encodeURIComponent(self.name()) +
                    '&sort_order=' + self.sort_order();
            }

            self.onHeaderMouseOver = function() {
                self.is_header_hovered(true);
            }

            self.onHeaderMouseOut = function() {
                self.is_header_hovered(false);
            }

            self.startEditStatus = function() {
                self.is_editing_status(true);
            }

            self.saveEditStatus = function() {
                if (self.name() == self.name.orig()) {
                    self.is_editing_status(false);
                } else {
                    var url = '{{ url('/task_statuses') }}/' + self.public_id();
                    var data = 'name=' + encodeURIComponent(self.name());
                    model.ajax('put', url, data, function(response) {
                        self.name.orig(self.name());
                        self.is_editing_status(false);
                    })
                }
            }

            self.onTaskDragged = function(dragged) {
                var task = dragged.item;
                task.task_status_sort_order(dragged.targetIndex);
                task.task_status_id(self.public_id());

                var url = '{{ url('/task_status_order') }}/' + task.public_id();
                var data = task.toData();
                model.ajax('put', url, data, function(response) {
                    // do nothing
                });
            }

            self.archiveStatus = function() {
                sweetConfirm(function() {
                    var url = '{{ url('/task_statuses') }}/' + self.public_id();
                    model.ajax('delete', url, null, function(response) {
                        model.statuses.remove(self);
                        if (model.statuses().length) {
                            var firstStatus = model.statuses()[0];
                            var adjustment = firstStatus.tasks().length;
                            for (var i=0; i<self.tasks().length; i++) {
                                var task = self.tasks()[i];
                                task.task_status_id(firstStatus.public_id());
                                task.task_status_sort_order(task.task_status_sort_order() + adjustment);
                                firstStatus.tasks.push(task);
                            }
                        } else {
                            location.reload();
                        }
                    })
                }, "{{ trans('texts.delete_status')}}");
            }

            self.cancelNewTask = function() {
                if (self.new_task.is_blank()) {
                    self.new_task.description('');
                }
                self.new_task.is_editing_task(false);
            }

            self.saveNewTask = function() {
                var task = self.new_task;
                var description = (task.description() || '').trim();
                if (! description) {
                    return false;
                }
                var task = new TaskModel({
                    description: description,
                    task_status_sort_order: self.tasks().length,
                })

                if (model.filter_client_id()) {
                    task.client(clientMap[model.filter_client_id()]);
                } else if (model.filter_project_id()) {
                    var project = projectMap[model.filter_project_id()];
                    task.project(project);
                    var client = clientMap[project.client.public_id()]
                    task.client(client);
                }

                task.task_status_id(self.public_id())

                var url = '{{ url('/tasks') }}';
                var data = task.toData();
                model.ajax('post', url, data, function(response) {
                    task.public_id(response.public_id);
                    self.tasks.push(task);
                    self.new_task.reset();
                })
            }

            if (data) {
                ko.mapping.fromJS(data, {}, this);
                self.name.orig(self.name());
            }
        }

        function TaskModel(data) {
            var self = this;
            self.public_id = ko.observable(0);
            self.description = ko.observable('');
            self.description.orig = ko.observable('');
            self.is_blank = ko.observable(false);
            self.is_editing_task = ko.observable(false);
            self.is_running = ko.observable(false);
            self.project = ko.observable();
            self.client = ko.observable();
            self.task_status_id = ko.observable();
            self.task_status_sort_order = ko.observable();
            self.is_panel_hovered = ko.observable(false);

            self.projectColor = ko.computed(function() {
                if (! self.project()) {
                    return '';
                }
                var projectId = self.project().public_id();
                var colorNum = (projectId-1) % 8;
                return 'project-group' + (colorNum+1);
            })

            self.startEditTask = function() {
                self.description.orig(self.description());
                self.is_editing_task(true);
                $('.kanban-column-row.editing textarea').focus();
            }

            self.onPanelMouseOver = function() {
                self.is_panel_hovered(true);
            }

            self.onPanelMouseOut = function() {
                self.is_panel_hovered(false);
            }

            self.toData = function() {
                var data = 'description=' + encodeURIComponent(self.description()) +
                    '&task_status_id=' + self.task_status_id() +
                    '&task_status_sort_order=' + self.task_status_sort_order();

                if (! self.public_id() && self.client()) {
                    data += '&client_id=' + self.client().public_id();
                }

                if (! self.public_id() && self.project()) {
                    data += '&project_id=' + self.project().public_id();
                }

                return data;
            }

            self.matchesFilter = function(filter, clientId, projectId) {
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

                if (clientId) {
                    if (! self.client() || self.client().public_id() != clientId) {
                        return false;
                    }
                }

                if (projectId) {
                    if (! self.project() || self.project().public_id() != projectId) {
                        return false;
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

                self.is_editing_task(false);
            }

            self.saveEditTask = function() {
                var description = (self.description() || '').trim();
                if (! description) {
                    return false;
                }

                var url = '{{ url('/tasks') }}/' + self.public_id();
                var data = self.toData();
                model.ajax('put', url, data, function(response) {
                    self.is_editing_task(false);
                });
            }

            self.viewTask = function() {
                window.open('{{ url('/tasks') }}/' + self.public_id() + '/edit', 'task');
            }

            self.reset = function() {
                self.is_editing_task(false);
                self.is_blank(true);
                self.client(false);
                self.project(false);
                self.description('');
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
                // resolve the private status id to the public value
                self.task_status_id(data.task_status ? data.task_status.public_id : 0);
            } else {
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
            self.contacts = ko.observableArray();

            self.displayName = ko.computed(function() {
                if (self.name()) {
                    return self.name();
                }

                if (self.contacts().length) {
                    return self.contacts()[0].displayName();
                }
            })

            self.mapping = {
                'contacts': {
                    create: function(options) {
                        return new ContactModel(options.data);
                    }
                }
            }

            if (data) {
                ko.mapping.fromJS(data, self.mapping, this);
            }
        }

        function ContactModel(data) {
            var self = this;
            self.public_id = ko.observable('');
            self.first_name = ko.observable('');
            self.last_name = ko.observable('');
            self.email = ko.observable('');
            self.phone = ko.observable('');

            if (data) {
                ko.mapping.fromJS(data, {}, this);
            }

            self.displayName = ko.computed(function() {
                if (self.first_name() || self.last_name()) {
                    return (self.first_name() || '') + ' ' + (self.last_name() || '');
                } else {
                    return self.email();
                }
            });
        }

        $(function() {
            $('#filter').typeahead({
                hint: true,
                highlight: true,
            },{
                name: 'data',
                limit: 4,
                display: 'value',
                source: searchData(clientList, 'tokens'),
                templates: {
                    header: '&nbsp;<span style="font-weight:600;font-size:15px">{{ trans('texts.clients') }}</span>'
                }
            },{
                name: 'data',
                limit: 4,
                display: 'value',
                source: searchData(projectList, 'tokens'),
                templates: {
                    header: '&nbsp;<span style="font-weight:600;font-size:15px">{{ trans('texts.projects') }}</span>'
                }
            }).on('typeahead:selected', function(element, datum, name) {
                model.filter(false);
                if (datum.type == 'client') {
                    model.filter_client_id(datum.id);
                    model.filter_project_id(false);
                } else {
                    model.filter_project_id(datum.id);
                    model.filter_client_id(false);
                }
            });

            $('#filter').on('input', function() {
                model.filter($('#filter').val());
                model.filter_client_id(false);
                model.filter_project_id(false);
            });

            window.model = new ViewModel();
            ko.applyBindings(model);

            if ({{ $projectPublicId ? 'true' : 'false' }}) {
                var project = projectMap[{{ $projectPublicId ?: 0}}];
                if (project) {
                    model.filter_project_id({{ $projectPublicId }});
                    model.filter(project.name());
                }
            } else if ({{ $clientPublicId ? 'true' : 'false' }}) {
                var client = clientMap[{{ $clientPublicId ?: 0 }}];
                if (client) {
                    model.filter_client_id({{ $clientPublicId }});
                    model.filter(client.displayName());
                }
            }

            $('.kanban').show();
        });

    </script>

    <ol class="breadcrumb">
        @if ($project)
            <li>{!! link_to('/tasks', trans('texts.tasks')) !!}</li>
            <li>{!! $project->present()->link !!}</li>
        @elseif ($client)
            <li>{!! link_to('/tasks', trans('texts.tasks')) !!}</li>
            <li>{!! $client->present()->link !!}</li>
        @else
            <li>{!! link_to('/tasks', trans('texts.tasks')) !!}</li>

        @endif
        <li class="active">{{ trans('texts.kanban') }}</li>
    </ol>


    <div class="kanban" style="display: none">
        <div data-bind="sortable: { data: statuses, as: 'status', afterMove: onStatusDragged, allowDrop: true, connectClass: 'connect-column' }" style="float:left">
            <div class="well kanban-column">

                <div class="kanban-column-header" data-bind="css: { editing: is_editing_status }, event: { mouseover: onHeaderMouseOver, mouseout: onHeaderMouseOut }">
                    <div class="pull-left" data-bind="event: { click: startEditStatus }">
                        <div class="view" data-bind="text: name().length > 24 ? name().substring(0, 24) + '...' : name()"></div>
                        <input class="edit" type="text" data-bind="value: name, valueUpdate: 'afterkeydown', hasfocus: is_editing_status, selected: is_editing_status,
                                event: { blur: saveEditStatus }, enterkey: saveEditStatus, escapekey: saveEditStatus"/>
                    </div>
                    <div class="pull-right" data-bind="click: archiveStatus, visible: is_header_hovered() &amp;&amp; ! is_editing_status()">
                        <i class="fa fa-times" title="{{ trans('texts.archive') }}"></i>
                    </div><br/>
                </div>

                <div data-bind="sortable: { data: tasks, as: 'task', afterMove: onTaskDragged, allowDrop: true, connectClass: 'connect-row' }" style="min-height:28px;">
                    <div class="kanban-column-row" data-bind="css: { editing: is_editing_task }, visible: task.matchesFilter($root.filter(), $root.filter_client_id(), $root.filter_project_id())">
                        <div class="view" data-bind="event: { click: startEditTask }">
                            <div class="panel" data-bind="css: { running: is_running, hovered: is_panel_hovered }, event: { mouseover: onPanelMouseOver, mouseout: onPanelMouseOut }">
                                <i class="fa fa-circle" data-bind="visible: project, css: projectColor"></i>
                                <div data-bind="text: description() &amp;&amp; description().length > 100 ? description().substring(0, 100) + '...' : description()"></div>
                            </div>
                        </div>
                        <div class="edit">
                            <textarea data-bind="value: description, valueUpdate: 'afterkeydown', enterkey: saveEditTask, escapekey: cancelEditTask"></textarea>
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

                <div class="kanban-column-row" data-bind="css: { editing: new_task.is_editing_task }, with: new_task" style="padding-bottom:6px">
                    <div class="view" data-bind="event: { click: startEditTask }">
                        <a href="#" class="text-muted" style="font-size:13px" data-bind="visible: is_blank">
                            {{ trans('texts.new_task') }}...
                        </a>
                    </div>
                    <div class="edit">
                        <textarea data-bind="value: description, valueUpdate: 'afterkeydown', enterkey: $parent.saveNewTask, escapekey: $parent.cancelNewTask"></textarea>
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
                <div class="view" data-bind="event: { click: startNewStatus }" style="padding-bottom: 8px;">
                    <a href="#" class="text-muted" style="font-size:13px">
                        {{ trans('texts.new_status') }}...
                    </a>
                </div>
                <div class="edit">
                    <input id="newStatusName" data-bind="value: new_status, valueUpdate: 'afterkeydown',
                        enterkey: saveNewStatus, escapekey: cancelNewStatus"></textarea>
                    <div class="pull-right" style="padding-top:6px">
                        <button type='button' class='btn btn-default btn-sm' data-bind="click: cancelNewStatus">
                            {{ trans('texts.cancel') }}
                        </button>
                        <!--
                        <button type='button' class='btn btn-success btn-sm' data-bind="click: saveNewStatus">
                            {{ trans('texts.save') }}
                        </button>
                        -->
                    </div>
                </div>
            </div>
        </div>

    </div>

@stop
