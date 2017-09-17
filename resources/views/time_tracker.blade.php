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
                    <input class="btn btn-normal btn-lg" type="button" value="{{ trans('texts.details') }}"> &nbsp;
                    <input class="btn btn-success btn-lg" type="button" value="{{ trans('texts.start') }}">
                </ul>
                <form>
                    <div class="input-group input-group-lg">
                        <span class="input-group-addon" style="width:1%;"><span class="glyphicon glyphicon-time"></span></span>
                        <input type="text" class="form-control"
                            placeholder="{{ trans('texts.what_are_you_working_on') }}" autocomplete="off" autofocus="autofocus">
                    </div>
                </form>
            </div>
        </div>
    </nav>

    <div style="height:74px"></div>

    <div class="list-group" data-bind="foreach: tasks">
        <a href="#" class="list-group-item list-group-item-type1">
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

            self.addTask = function(task) {
                self.tasks.push(task);
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
                console.log(task);
                var taskModel = new TaskModel(task);
                model.addTask(taskModel);
            }
            ko.applyBindings(model);
        });

    </script>

@stop
