<script type="text/javascript">

    function intToTime(seconds)
    {
        if (seconds === null) {
            return null;
        }

        // calculate seconds, minutes, hours
        var duration = seconds*1000
        var milliseconds = parseInt((duration%1000)/100)
            , seconds = parseInt((duration/1000)%60)
            , minutes = parseInt((duration/(1000*60))%60)
            , hours = parseInt((duration/(1000*60*60))%24);

        hours = (hours < 10) ? "0" + hours : hours;
        minutes = (minutes < 10) ? "0" + minutes : minutes;
        seconds = (seconds < 10) ? "0" + seconds : seconds;

        return new Date(1970, 0, 1, hours, minutes, seconds, 0);
    }

    ko.bindingHandlers.datepicker = {
        init: function (element, valueAccessor, allBindingsAccessor) {
           $(element).datepicker();
           $(element).change(function() {
              var value = valueAccessor();
              value($(element).datepicker('getDate'));
           })
        },
        update: function (element, valueAccessor) {
           var value = ko.utils.unwrapObservable(valueAccessor());
           if (value) {
               $(element).datepicker('setDate', new Date(value * 1000));
           }
        }
    };

    ko.bindingHandlers.timepicker = {
        init: function (element, valueAccessor, allBindingsAccessor) {
           var options = allBindingsAccessor().timepickerOptions || {};
           $.extend(options, {
               wrapHours: false,
               showDuration: true,
               step: 15,
           });
           $(element).timepicker(options);

           ko.utils.registerEventHandler(element, 'change', function () {
             var value = valueAccessor();
             var seconds = $(element).timepicker('getSecondsFromMidnight');
             value(seconds);
             /*
             var field = $(element).attr('name');
             var time = 0;
             if (field == 'duration') {
                time = $(element).timepicker('getSecondsFromMidnight');
             } else {
                 var dateTime = $(element).timepicker('getTime');
                 if (dateTime) {
                     time = dateTime.getTime() / 1000;
                 }
             }
             value(time);
             */
           });
        },
        update: function (element, valueAccessor) {
          var value = ko.utils.unwrapObservable(valueAccessor());
          var field = $(element).attr('name');

          if (value) {
              if (field == 'duration') {
                  $(element).timepicker('setTime', intToTime(value));
              } else {
                  $(element).timepicker('setTime', new Date(value * 1000));
              }
          }

          if (field == 'start_time') {
              setTimeout(function() {
                  $input = $(element).closest('td').next('td').find('input').show();
                  $input.timepicker('option', 'durationTime', $(element).val());
              }, 1);
          }
        }
    };


    function ViewModel() {
        var self = this;
        self.tasks = ko.observableArray();
        self.filter = ko.observable('');
        self.clock = ko.observable(0);
        self.formChanged = ko.observable(false);
        self.isStartEnabled = ko.observable(true);
        self.isSaveEnabled = ko.observable(true);

        self.selectedTask = ko.observable(false);
        self.selectedClient = ko.observable(false);
        self.selectedProject = ko.observable(false);

        var defaultSortField = 'createdAt';
        var defaultSortDirection = 'descending';
        if (isStorageSupported()) {
            defaultSortField = localStorage.getItem('last:time_tracker:sort_field') || defaultSortField;
            defaultSortDirection = localStorage.getItem('last:time_tracker:sort_direction') || defaultSortDirection;
        }

        self.filterState = ko.observable('all');
        self.sortField = ko.observable(defaultSortField);
        self.sortDirection = ko.observable(defaultSortDirection);

        self.isDesktop = function() {
            return navigator.userAgent == 'Time Tracker';
        }

        self.onSaveClick = function() {
            if (! model.selectedTask() || ! model.formChanged()) {
                return;
            }
            model.selectedTask().save(true);
        }

        self.onSortChange = function() {
            if (isStorageSupported()) {
                localStorage.setItem('last:time_tracker:sort_field', self.sortField());
                localStorage.setItem('last:time_tracker:sort_direction', self.sortDirection());
            }
        }

        self.onFilterClick = function(event) {
            $('#filterPanel').toggle();
        }

        self.onRefreshClick = function() {
            if (self.isDesktop()) {
                if (model.selectedTask() && model.formChanged()) {
                    swal("{{ trans('texts.save_or_discard') }}");
                    return false;
                } else {
                    location.reload();
                }
            } else {
                location.reload();
            }
        }

        self.refreshTitle = function() {
            var tasks = self.tasks();
            var count = 0;
            for (var i=0; i<tasks.length; i++) {
                var task = tasks[i];
                if (task.isRunning()) {
                    count++;
                }
            }
            var title = '';
            if (count > 0) {
                title = '(' + count + ') ';
            }
            title += "{{ trans('texts.time_tracker') }} | {{ APP_NAME }}";
            document.title = title;
        }

        self.submitBulkAction = function(action, task) {
            if (! task || ! action) {
                return false;
            }
            var data = {
                id: task.public_id(),
                action: action,
            }
            self.isStartEnabled(false);
            $.ajax({
                dataType: 'json',
                type: 'post',
                data: data,
                url: '{{ url('/tasks/bulk') }}',
                accepts: {
                    json: 'application/json'
                },
                success: function(response) {
                    console.log(response);
                    if (action == 'archive' || action == 'delete') {
                        self.removeTask(task);
                        self.selectTask(false);
                        $('#search').focus();
                    }
                    self.refreshTitle();
                    if (action == 'archive') {
                        toastr.success("{{ trans('texts.archived_task') }}");
                    } else if (action == 'delete') {
                        toastr.success("{{ trans('texts.deleted_task') }}");
                    }
                },
                error: function(error) {
                    toastr.error("{{ trans('texts.error_refresh_page') }}");
                }
            }).always(function() {
                setTimeout(function() {
                    model.isStartEnabled(true);
                }, 1000);
            });
        }

        self.onDeleteClick = function() {
            sweetConfirm(function() {
                self.submitBulkAction('delete', self.selectedTask());
            }, "{{ trans('texts.delete_task') }}");

            return false;
        }

        self.onArchiveClick = function() {
            sweetConfirm(function() {
                self.submitBulkAction('archive', self.selectedTask());
            }, "{{ trans('texts.archive_task') }}");

            return false;
        }

        self.onCancelClick = function() {
            sweetConfirm(function() {
                var task = self.selectedTask();
                if (task.isNew()) {
                    self.selectedTask(false);
                    self.removeTask(task);
                    // wait for it to be re-enabled
                    setTimeout(function() {
                        $('#search').focus();
                    }, 1);
                } else {
                    task.update(task.data);
                }
                self.formChanged(false);

            }, "{{ trans('texts.discard_changes') }}");
            return false;
        }

        self.onFilterFocus = function(data, event) {
            if (model.selectedTask() && model.formChanged()) {
                return;
            }
            self.selectedTask(false);
        }

        self.onFilterChanged = function(data) {
            self.selectedTask(false);
            self.selectedClient(false);
            self.selectedProject(false);
        }

        self.onFilterKeyPress = function(data, event) {
            if (event.which == 13) {
                self.onStartClick();
            }
            return true;
        }

        self.onFormChange = function(data, event) {
            self.formChanged(true);
            return true;
        }

        self.onFormKeyPress = function(data, event) {
            if (event.which == 13) {
                if (event.target.type == 'textarea') {
                    return true;
                }
                self.onSaveClick();
            }
            return true;
        }

        self.viewClient = function(task) {
            if (model.selectedTask() && model.formChanged()) {
                swal("{{ trans('texts.save_or_discard') }}");
                return false;
            }
            var client = task.client();
            if (self.selectedClient() && self.selectedClient().public_id() == client.public_id()) {
                self.filter('');
                self.selectedClient(false);
            } else {
                self.filter(client.displayName());
                self.selectedProject(false);
                self.selectedClient(client);
            }
            $('#search').focus();
            return false;
        }

        self.viewProject = function(task) {
            if (model.selectedTask() && model.formChanged()) {
                swal("{{ trans('texts.save_or_discard') }}");
                return false;
            }
            var project = task.project();
            if (self.selectedProject() && self.selectedProject().public_id() == project.public_id()) {
                self.filter('');
                self.selectedProject(false);
            } else {
                self.filter(project.name());
                self.selectedClient(false);
                self.selectedProject(project);
            }
            $('#search').focus();
            return false;
        }

        self.onStartClick = function() {
            if (self.selectedTask()) {
                self.selectedTask().onStartClick();
            } else {
                var time = new TimeModel();
                time.startTime(moment().unix());
                var task = new TaskModel();
                if (self.selectedProject()) {
                    task.setProject(self.selectedProject());
                } else if (self.selectedClient()) {
                    task.setClient(self.selectedClient());
                } else {
                    task.description(self.filter());
                }
                task.addTime(time);
                self.selectedTask(task);
                self.addTask(task);
                model.refreshTitle();
                model.formChanged(true);
                self.filter('');
                task.focus();
            }
        }

        self.tock = function(startTime) {
            self.clock(self.clock() + 1);
            setTimeout(function() {
                model.tock();
            }, 1000);
        }

        self.filterStyle = ko.computed(function() {
            return 'background-color: ' + (self.filter() ? '#ffffaa' : 'white') + ' !important';
        });

        self.statistics = ko.computed(function() {
            return '';
        });

        self.showArchive = ko.computed(function() {
            var task = self.selectedTask();
            if (! task) {
                return false;
            }
            return task.isCreated() && ! self.formChanged();
        });

        self.showCancel = ko.computed(function() {
            var task = self.selectedTask();
            if (! task) {
                return false;
            }
            return task.isNew() || self.formChanged();
        });

        self.startIcon = ko.computed(function() {
            if (self.selectedTask()) {
                return self.selectedTask().startIcon();
            } else {
                return 'glyphicon glyphicon-play';
            }
        });

        self.startLabel = ko.computed(function() {
            if (self.selectedTask()) {
                if (self.selectedTask().isRunning()) {
                    return "{{ trans('texts.stop') }}";
                } else {
                    return "{{ trans('texts.resume') }}";
                }
            } else {
                return "{{ trans('texts.start') }}";
            }
        });

        self.startClass = ko.computed(function() {
            if (self.selectedTask()) {
                return self.selectedTask().startClass();
            } else {
                return 'btn-success';
            }
        });

        self.placeholder = ko.computed(function() {
            if (self.selectedTask()) {
                if (self.selectedTask().description()) {
                    return self.selectedTask().description();
                } else {
                    return "{{ trans('texts.no_description') }}"
                }
            } else {
                return "{{ trans('texts.what_are_you_working_on') }}";
            }
        });

        self.taskById = function(taskId) {
            var tasks = self.tasks();
            for (var i=0; i<tasks.length; i++) {
                var task = tasks[i];
                if (task.public_id() == taskId) {
                    return task;
                }
            }
            return false;
        }

        self.filteredTasks = ko.computed(function() {
            var tasks = self.tasks();

            var filtered = ko.utils.arrayFilter(tasks, function(task) {
                return task.matchesFilter(self.filter(), self.filterState());
            });

            if (! self.filter() || filtered.length > 0) {
                tasks = filtered;
            }

            // sort the data
            tasks.sort(function (left, right) {
                var sortField = self.sortField();
                var leftSortValue = left.sortValue(sortField);
                var rightSortValue = right.sortValue(sortField);
                if (sortField == 'createdAt' || sortField == 'duration') {
                    if (self.sortDirection() == 'descending') {
                        return rightSortValue - leftSortValue
                    } else {
                        return leftSortValue - rightSortValue;
                    }
                } else {
                    if (self.sortDirection() == 'ascending') {
                        return leftSortValue.localeCompare(rightSortValue);
                    } else {
                        return rightSortValue.localeCompare(leftSortValue);
                    }
                }
            });

            return tasks;
        });

        self.addTask = function(task) {
            self.tasks.push(task);
            self.formChanged(true);
        }

        self.removeTask = function(task) {
            self.tasks.remove(task);
        }

        self.selectTask = function(task) {
            if (model.selectedTask() && model.formChanged()) {
                swal("{{ trans('texts.save_or_discard') }}");
                return false;
            }

            if (task == self.selectedTask()) {
                task = false;
            }

            // if a client is selected the project list will be filtered
            // this prevents the new task's project from being show
            // to fix it we're clearing the list and then firing a
            // client change event to re-filter the list
            refreshProjectList(true);

            self.selectedTask(task);

            if (task) {
                task.focus();
                if (! task.project()) {
                    // trigger client change to show all projects in autocomplete
                    $('select#client_id').trigger('change');
                }
            } else {
                $('#search').focus();
            }

            if (isStorageSupported()) {
                localStorage.setItem('last:time_tracker:task_id', task ? task.public_id() : 0);
            }

            self.formChanged(false);
        }
    }

    function TaskModel(data) {
        var self = this;
        self.public_id = ko.observable();
        self.description = ko.observable('');
        self.time_log = ko.observableArray();
        self.client_id = ko.observable();
        self.project_id = ko.observable();
        self.client = ko.observable();
        self.project = ko.observable();
        self.isHovered = ko.observable(false);
        self.created_at = ko.observable(moment().format('YYYY-MM-DD HH:mm:ss'));

        self.mapping = {
            'client': {
                update: function(data) {
                    if (! data.data) {
                        self.client_id(0);
                        return false;
                    } else {
                        self.client_id(data.data.public_id);
                        return new ClientModel(data.data);
                    }
                }
            },
            'project': {
                update: function(data) {
                    if (! data.data) {
                        self.project_id(0);
                        return false;
                    } else {
                        self.project_id(data.data.public_id);
                        return new ProjectModel(data.data);
                    }
                },
            },
            'ignore': [
                'time_log',
                'client_id',
                'project_id',
            ]
        }

        self.isValid = function() {
            var client = self.client();
            var project = self.project();

            if (client && client.public_id() != self.client_id()) {
                return "Client id's don't match " + client.public_id() + " " + self.client_id();
            }

            if (project) {
                if (project.public_id() != self.project_id()) {
                    return "Project id's don't match " + project.public_id() + " " + self.project_id();
                }
                if (project.public_id() != -1) {
                    var client = projectMap[project.public_id()].client;
                    if (client.public_id != self.client_id()) {
                        return "Client and project id's don't match " + client.public_id + " " + self.client_id();
                    }
                }
            }

            return true;
        }

        self.focus = function() {
            if (! self.client()) {
                $('.client-select input.form-control').focus();
            } else if (! self.project()) {
                $('.project-select input.form-control').focus();
            } else {
                $('#description').focus();
            }
        }

        self.save = function(isSelected) {
            if (self.isValid() !== true) {
                toastr.error("{{ trans('texts.error_refresh_page') }}");
                throw self.isValid();
                return;
            }

            var data = 'client_id=' + self.client_id()
                            + '&project_id=' + self.project_id()
                            + '&project_name=' + encodeURIComponent(self.project() ? self.project().name() : '')
                            + '&description=' + encodeURIComponent(self.description())
                            + '&time_log=' + JSON.stringify(self.times());

            var url = '{{ url('/tasks') }}';
            var method = 'post';
            if (self.public_id()) {
                method = 'put';
                url += '/' + self.public_id();
            }
            if (self.isRunning()) {
                data += '&is_running=1';
            } else {
                data += '&is_running=0';
            }
            model.isSaveEnabled(false);
            $.ajax({
                dataType: 'json',
                type: method,
                data: data,
                url: url,
                accepts: {
                    json: 'application/json'
                },
                success: function(response) {
                    if (isSelected) {
                        var clientId = $('input[name=client_id]').val();
                        if (clientId == -1 && response.client) {
                            var client = response.client;
                            clients.push(client);
                            addClientToMaps(client);
                            refreshClientList();
                        }
                        var projectId = $('input[name=project_id]').val();
                        if (projectId == -1 && response.project) {
                            var project = response.project;
                            project.client = response.client;
                            projects.push(project);
                            addProjectToMaps(project);
                            refreshProjectList(true);
                        }
                        var isNew = !self.public_id();
                        self.update(response);
                        model.formChanged(false);
                        if (isStorageSupported()) {
                            localStorage.setItem('last:time_tracker:task_id', self.public_id());
                        }
                        if (isNew) {
                            toastr.success("{{ trans('texts.created_task') }}");
                        } else {
                            toastr.success("{{ trans('texts.updated_task') }}");
                        }
                    } else {
                        self.update(response);
                        if (self.isRunning()) {
                            if (self.time_log().length == 1) {
                                toastr.success("{{ trans('texts.started_task') }}");
                            } else {
                                toastr.success("{{ trans('texts.resumed_task') }}");
                            }
                        } else {
                            toastr.success("{{ trans('texts.stopped_task') }}");
                        }
                    }
                    model.refreshTitle();
                },
                error: function(error) {
                    toastr.error("{{ trans('texts.error_refresh_page') }}");
                },
            }).always(function() {
                setTimeout(function() {
                    model.isSaveEnabled(true);
                    model.isStartEnabled(true);
                }, 1000);
            });
        }

        self.update = function(data) {
            self.data = data;
            var times = data.time_log instanceof Array ? data.time_log : JSON.parse(data.time_log);
            ko.mapping.fromJS(data, self.mapping, this);
            self.time_log.removeAll();
            for (var i=0; i<times.length; i++) {
                self.time_log.push(new TimeModel(times[i]));
            }
            self.checkForEmpty();
        }

        self.checkForEmpty = function() {
            var hasEmpty = false;
            var lastTime = 0;
            for (var i=0; i<self.time_log().length; i++) {
                var timeLog = self.time_log()[i];
                if (timeLog.isEmpty() || timeLog.isRunning()) {
                    hasEmpty = true;
                }
            }
            if (!hasEmpty) {
                self.addTime();
            }
        }

        self.sortValue = function(field) {
            if (field == 'client') {
                return self.client() && self.client().displayName() ? self.client().displayName().toLowerCase() : '';
            } else if (field == 'project') {
                return self.project() && self.project().name() ? self.project().name().toLowerCase() : '';
            } else if (field == 'duration') {
                return self.seconds(true);
            } else {
                return self[field]();
            }
        }

        self.isNew = ko.computed(function() {
            return ! self.public_id();
        });

        self.isCreated = ko.computed(function() {
            return self.public_id();
        });

        self.isRunning = ko.computed(function() {
            var timeLog = self.time_log();
            if (! timeLog.length) {
                return false;
            }
            var time = timeLog[timeLog.length-1];
            return time.isRunning();
        });

        self.actionButtonVisible = ko.computed(function() {
            return self.isHovered();
        });

        self.hasFocus = function() {
            console.log('focused... ' + self.public_id());
        }

        self.onMouseOver = function() {
            self.isHovered(true);
        }

        self.onMouseOut = function() {
            self.isHovered(false);
        }

        self.addTime = function(time) {
            if (!time) {
                time = new TimeModel();
            }
            self.time_log.push(time);
        }

        self.times = function() {
            var times = [];
            for (var i=0; i<self.time_log().length; i++) {
                var timeLog = self.time_log()[i];
                if (! timeLog.isEmpty()) {
                    times.push([timeLog.startTime(),timeLog.endTime()]);
                }
            }
            return times;
        }

        self.matchesFilter = function(filter, filterState) {
            if (filter) {
                filter = model.filter().toLowerCase();
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

            if (filterState == 'stopped' && self.isRunning()) {
                return false;
            } else if (filterState == 'running' && ! self.isRunning()) {
                return false;
            }

            return true;
        }

        self.onStartClick = function() {
            if (! model.isStartEnabled()) {
                return false;
            }

            if (self.isRunning()) {
                var time = self.lastTime();
                time.endTime(moment().unix());
            } else {
                var time = new TimeModel();
                time.startTime(moment().unix());
                self.addTime(time);
            }

            if (self.public_id()) {
                var selectedTask = model.selectedTask();
                if (model.formChanged() && selectedTask && selectedTask.public_id() == self.public_id()) {
                    model.onSaveClick();
                } else {
                    model.isStartEnabled(false);
                    self.save();
                }
            }
        }

        self.listItemState = ko.computed(function() {
            var str = '';
            if (self == model.selectedTask()) {
                str = 'active';

                if (! self.public_id() || model.formChanged()) {
                    str += ' changed fade-color';
                }
            }
            if (self.isRunning()) {
                str += ' list-group-item-running';
            }
            if (! self.project()) {
                return str;
            }
            var projectId = self.project().public_id();
            var colorNum = (projectId-1) % 8;
            return str + ' list-group-item-type' + (colorNum+1);

        });

        self.clientName = ko.computed(function() {
            return self.client() ? self.client().displayName() : '';
        });

        self.projectName = ko.computed(function() {
            return self.project() ? self.project().name() : '';
        });

        self.startClass = ko.computed(function() {
            if (! model.isStartEnabled()) {
                return 'disabled';
            }

            return self.isRunning() ? 'btn-danger' : 'btn-success';
        });

        self.startIcon = ko.computed(function() {
            return self.isRunning() ? 'glyphicon glyphicon-stop' : 'glyphicon glyphicon-play';
        });

        self.setClient = function(client) {
            self.client(client);
            self.client_id(client.public_id());
        }

        self.setProject = function(project) {
            self.project(project);
            self.project_id(project.public_id());

            var client = projectMap[project.public_id()].client;
            self.setClient(new ClientModel(client));
        }

        self.createdAt = function() {
            return moment(self.created_at()).unix();
        }

        self.firstTime = function() {
            return self.time_log()[0];
        }

        self.lastTime = function() {
            var times = self.time_log();
            return times[times.length-1];
        }

        self.age = ko.computed(function() {
            if (! self.time_log().length) {
                return '';
            }
            var time = self.firstTime();
            return time.age();
        });

        self.seconds = function(total) {
            if (! self.time_log().length) {
                return moment.duration(0);
            }
            var time = self.lastTime();
            var now = new Date().getTime();
            var duration = 0;
            if (time.isRunning() && ! total) {
                var duration = now - (time.startTime() * 1000);
                duration = Math.floor(duration / 100) / 10;
            } else {
                self.time_log().forEach(function(time){
                    duration += time.duration();
                });
            }

            return moment.duration(duration * 1000);
        }

        self.totalDuration = ko.computed(function() {
            model.clock(); // bind to the clock
            var duration = self.seconds(true);
            return Math.floor(duration.asHours()) + moment.utc(duration.asMilliseconds()).format(":mm:ss");
        });

        self.duration = ko.computed(function() {
            model.clock(); // bind to the clock
            var duration = self.seconds(false);
            return Math.floor(duration.asHours()) + moment.utc(duration.asMilliseconds()).format(":mm:ss");
        });

        self.removeTime = function(time) {
            model.formChanged(true);
            self.time_log.remove(time);
            self.checkForEmpty();
        }

        if (data) {
            self.update(data);
        }
    }

    function ProjectModel(data) {
        var self = this;
        self.name = ko.observable('');
        self.public_id = ko.observable(-1);

        if (data) {
            ko.mapping.fromJS(data, {}, this);
        }
    }

    function ClientModel(data) {
        var self = this;
        self.name = ko.observable('');
        self.public_id = ko.observable(-1);
        self.contacts = ko.observableArray();

        self.mapping = {
            'contacts': {
                create: function(options) {
                    return new ContactModel(options.data);
                }
            }
        }

        self.displayName = ko.computed(function() {
            if (self.name()) {
                return self.name();
            }
            if (self.contacts().length == 0) return;
            var contact = self.contacts()[0];
            if (contact.first_name() || contact.last_name()) {
                return contact.first_name() + ' ' + contact.last_name();
            } else {
                return contact.email();
            }
        });

        if (data) {
            ko.mapping.fromJS(data, self.mapping, this);
        }
    }

    function ContactModel(data) {
        var self = this;
        self.first_name = ko.observable('');
        self.last_name = ko.observable('');
        self.email = ko.observable('');

        if (data) {
            ko.mapping.fromJS(data, {}, this);
        }

        self.displayName = ko.computed(function() {
            if (self.first_name() || self.last_name()) {
                return (self.first_name() || '') + ' ' + (self.last_name() || '') + ' ';
            } else if (self.email()) {
                return self.email();
            } else {
                return '';
            }
        });
    }

    function TimeModel(data) {
        var self = this;
        self.startTime = ko.observable(0);
        self.endTime = ko.observable(0);
        self.isStartValid = ko.observable(true);
        self.isEndValid = ko.observable(true);
        self.isHovered = ko.observable(false);

        if (data) {
            self.startTime(data[0]);
            self.endTime(data[1]);
        };

        self.actionButtonVisible = ko.computed(function() {
            return self.isHovered() && ! self.isEmpty();
        });

        self.onMouseOver = function() {
            self.isHovered(true);
        }

        self.onMouseOut = function() {
            self.isHovered(false);
        }

        self.startDateMidnight = function() {
            return moment.unix(self.startTime()).set('hours', 0).set('minutes', 0).set('seconds', 0);
        }

        self.startTimeOfDay = ko.computed({
            read: function () {
                return self.startTime();
            },
            write: function(value) {
                if (self.startTime()) {
                    var orig = self.startDateMidnight().unix();
                } else {
                    var orig = moment().set('hours', 0).set('minutes', 0).set('seconds', 0).unix();
                }
                self.startTime(orig + value);
            }
        });

        self.endTimeOfDay = ko.computed({
            read: function () {
                return self.endTime();
            },
            write: function(value) {
                self.endTime(self.startDateMidnight().unix() + value);
            }
        });


        self.startDate = ko.computed({
            read: function () {
                return self.startTime();
            },
            write: function(value) {
                var origVal = self.startDateMidnight();
                var newVal = moment(value).set('hours', 0);
                var diff = newVal.diff(origVal, 'days') * 60 * 60 * 24;
                if (self.startTime()) {
                    self.startTime(self.startTime() + diff);
                    console.log('update start to: ' + self.startTime());
                    if (self.endTime()) {
                        self.endTime(self.endTime() + diff);
                    }
                } else {
                    self.startTime(newVal.unix());
                    //self.startTime(value);
                    console.log('set start to: ' + self.startTime());
                }
            }
        });

        self.order = ko.computed(function() {
            return self.startTime();
        });

        self.isEmpty = ko.computed(function() {
            return ! self.startTime() && ! self.endTime();
        });

        self.isRunning = ko.computed(function() {
            return self.startTime() && ! self.endTime();
        });

        self.age = ko.computed(function() {
            model.clock(); // bind to the clock
            return moment.unix(self.startTime()).fromNow();
        });

        self.duration = ko.computed({
            read: function () {
                model.clock(); // bind to the clock
                if (! self.startTime()) {
                    return false;
                }
                var endTime = self.endTime() ? self.endTime() : moment().unix();
                return endTime - self.startTime();
            },
            write: function(value) {
                self.endTime(self.startTime() + value);
            }
        });


        /*
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
                return Math.floor(duration.asHours()) + moment.utc(duration.asMilliseconds()).format(":mm:ss");
            },
            write: function(data) {
                self.endTime(self.startTime() + convertToSeconds(data));
            }
        });
        */
    }

</script>
