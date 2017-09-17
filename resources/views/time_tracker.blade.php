@extends('master')

@section('head_css')
    <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

    <style type="text/css">

        .list-group-item:before {
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 6px;
            content: "";
        }

        .list-group-item-type1:before {
            background-color: purple;
        }

    </style>

@stop

@section('body')
    <nav class="navbar navbar-default navbar-fixed-top">
        <div class="container-fluid">
            <div class="navbar-header" style="padding-top:12px;padding-bottom:12px;">
                <ul class="nav navbar-nav navbar-right" style="padding-right:12px; padding-left:10px">
                    <button type='button' class='btn btn-normal btn-lg'>
                        {{ trans('texts.details') }}
                    </button> &nbsp;
                    <button type='button' class='btn btn-success btn-lg' data-bind="click: onStartClick">
                        <span data-bind="text: startLabel"></span>
                        <span class='glyphicon glyphicon-play'></span>
                    </button>
                </ul>
                <div class="input-group input-group-lg">
                    <span class="input-group-addon" style="width:1%;"><span class="glyphicon glyphicon-time"></span></span>
                    <input type="text" class="form-control search" data-bind="event: { input: onFilterChanged }, value: filter, valueUpdate: 'afterkeydown', attr: {placeholder: placeholder}"
                        autocomplete="off" autofocus="autofocus">
                </div>
            </div>
        </div>
    </nav>

    <div style="height:74px"></div>

    <div class="well" style="padding-bottom:0px;margin-bottom:0px;">
        <div class="panel panel-default">
            <div class="panel-body">
                {!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
                {!! Former::select('project_id')
                        ->addOption('', '')
                        ->addGroupClass('project-select')
                        ->label(trans('texts.project')) !!}
                {!! Former::textarea('description')->data_bind('value: selectedTask().description')->rows(4) !!}
            </div>
        </div>
    </div>


    <div class="list-group" data-bind="foreach: filteredTasks">
        <a href="#" data-bind="click: $parent.selectTask" class="list-group-item list-group-item-type1">
            <span class="pull-right">
                14
            </span>
            <h5 class="list-group-item-heading" data-bind="text: description"></h5>
            <p class="list-group-item-text">...

            </p>
        </a>
    </div>

    <script type="text/javascript">

        var tasks = {!! $tasks !!};

        function ViewModel() {
            var self = this;
            self.tasks = ko.observableArray();
            self.filter = ko.observable('');
            self.selectedTask = ko.observable(false);

            self.onFilterChanged = function(data) {
                self.selectedTask(false);
            }

            self.onStartClick = function() {
                if (self.selectedTask()) {

                } else {
                    var task = new TaskModel();
                    task.description(self.filter());
                    self.addTask(task);
                    self.selectedTask(task);
                    self.filter('');
                }
            }

            self.startLabel = ko.computed(function() {
                if (self.selectedTask()) {
                    return "{{ trans('texts.resume') }}";
                } else {
                    return "{{ trans('texts.start') }}";
                }
            });

            self.placeholder = ko.computed(function() {
                if (self.selectedTask() && self.selectedTask().description) {
                    return self.selectedTask().description();
                } else {
                    return "{{ trans('texts.what_are_you_working_on') }}";
                }
            });

            self.filteredTasks = ko.computed(function() {
                if(! self.filter()) {
                    return self.tasks();
                } else {
                    var filtered = ko.utils.arrayFilter(self.tasks(), function(task) {
                        var description = task.description().toLowerCase();
                        return description.indexOf(self.filter().toLowerCase()) >= 0;
                    });
                    return filtered.length == 0 ? self.tasks() : filtered;
                }
            });

            self.addTask = function(task) {
                console.log(task);
                self.tasks.push(task);
            }

            self.selectTask = function(task) {
                self.filter('');
                self.selectedTask(task);
            }
        }

        function TaskModel(data) {
            var self = this;
            self.description = ko.observable('test');

            if (data) {
                ko.mapping.fromJS(data, {}, this);
            }
        }

        $(function() {
            window.model = new ViewModel();
            for (var i=0; i<tasks.length; i++) {
                var task = tasks[i];
                var taskModel = new TaskModel(task);
                model.addTask(taskModel);
            }
            ko.applyBindings(model);
        });

    </script>

@stop
