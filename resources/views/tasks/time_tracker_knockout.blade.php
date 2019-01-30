<script type="text/javascript">

    function intToTime(seconds) {
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
               lang: {
                   @foreach(['AM' , 'PM', 'mins', 'hr', 'hrs'] as $field)
                        "{{ $field }}": "{{ trans('texts.time_' . strtolower($field)) }}",
                   @endforeach
               }
           });
           $(element).timepicker(options);

           ko.utils.registerEventHandler(element, 'change', function () {
             var value = valueAccessor();
             var field = $(element).attr('name');
             var seconds = $(element).timepicker('getSecondsFromMidnight');

             // add 24 hours the end time is before the start time/tomorrow
             if (field == 'end_time' && seconds !== null) {
                 $input = $(element).closest('td').prev('td').find('input');
                 var startTime = $input.timepicker('getSecondsFromMidnight');
                 if (seconds < startTime) {
                     seconds += 60 * 60 * 24;
                 }
             }

             value(seconds);
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
          } else {
              $(element).val('');
          }

          if (field == 'start_time') {
              setTimeout(function() {
                  $input = $(element).closest('td').next('td').find('input');
                  var time = $(element).val();
                  if (time) {
                      // round the end time to the next 15 minutes
                      var seconds = $(element).timepicker('getSecondsFromMidnight');
                      var minTime = moment.utc(seconds * 1000).seconds(0);
                      var minutes = minTime.minutes();
                      if (minutes >= 45) {
                          minTime.minutes(0).add(1, 'hour');
                      } else if (minutes >= 30) {
                          minTime.minutes(45);
                      } else if (minutes >= 15) {
                          minTime.minutes(30);
                      } else {
                          minTime.minutes(15);
                      }
                      $input.timepicker('option', 'minTime', minTime.format("H:mm:ss"));
                      $input.timepicker('option', 'durationTime', time);
                  }
              }, 1);
          }
        }
    };

    var defaultTimes = [];
    for (var i=15; i<(15*4*6); i+=15) {
        var time = moment.utc(i * 1000 * 60).format("H:mm:ss");
        defaultTimes.push(time);
    }

    var timeMatcher = function(strs) {
      return function findMatches(q, cb) {
        var matches, substringRegex;
        matches = [];
        substrRegex = new RegExp(q, 'i');
        $.each(strs, function(i, str) {
          if (substrRegex.test(str)) {
            matches.push(str);
          }
        });
        cb(matches);
      };
    };

    ko.bindingHandlers.typeahead = {
        init: function (element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
            var $element = $(element);
            var allBindings = allBindingsAccessor();
            $element.typeahead({
                highlight: true,
                minLength: 0,
            },
            {
                name: 'times',
                source: timeMatcher(defaultTimes)
            }).on('typeahead:change', function(element, datum, name) {
                var value = valueAccessor();
                if (datum && datum.indexOf(':') >= 0) {
                    var duration = moment.duration(datum).asSeconds();
                } else {
                    var duration = parseFloat(datum) * 60 * 60;
                }
                value(duration);
            }).on('typeahead:select', function(element, datum, name) {
                var value = valueAccessor();
                var duration = moment.duration(datum).asSeconds();
                value(duration);
            });
        },

        update: function (element, valueAccessor) {
            var value = ko.utils.unwrapObservable(valueAccessor());
            if (value) {
                var duration = moment.duration(value * 1000);
                var value = Math.floor(duration.asHours()) + moment.utc(duration.asMilliseconds()).format(":mm:ss")
                $(element).typeahead('val', value);
            } else {
                $(element).typeahead('val', '');
            }
        }
    };


    function ViewModel() {
        var self = this;
        self.tasks = ko.observableArray();
        self.filter = ko.observable('');
        self.clock = ko.observable(0);

        self.sendingRequest = ko.observable(false);
        self.sendingBulkRequest = ko.observable(false);

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
        self.filterStatusId = ko.observable(false);
        self.sortField = ko.observable(defaultSortField);
        self.sortDirection = ko.observable(defaultSortDirection);

        self.isDesktop = function() {
            return navigator.userAgent == "{{ TIME_TRACKER_USER_AGENT }}";
        }

        self.isStartEnabled = ko.computed(function() {
            return ! self.sendingRequest();
        });

        self.isChanged = ko.computed(function() {
            return self.selectedTask() && self.selectedTask().isChanged();
        });

        self.isSaveEnabled = ko.computed(function() {
            return self.selectedTask() && self.selectedTask().isChanged() && ! self.sendingRequest();
        });

        self.onSaveClick = function() {
            if (! model.selectedTask() || ! model.isChanged()) {
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

        self.onClearClick = function() {
            self.filter('');
            $('#search').focus();
        }

        self.onRefreshClick = function() {
            if (self.isDesktop()) {
                if (model.selectedTask() && model.isChanged()) {
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
            self.sendingBulkRequest(true);
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
                    model.sendingBulkRequest(false);
                }, 1500);
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
            }, "{{ trans('texts.discard_changes') }}");
            return false;
        }

        self.onFilterFocus = function(data, event) {
            if (model.selectedTask() && model.isChanged()) {
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
            if (model.isChanged()) {
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
            if (model.isChanged()) {
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
                    setTimeout(function() {
                        $('select#client_id').trigger('change');
                    }, 1);
                } else {
                    task.description(self.filter());
                }
                task.addTime(time);
                self.selectedTask(task);
                self.addTask(task);
                model.refreshTitle();
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
            return task.isCreated() && ! task.isChanged();
        });

        self.showDiscard = ko.computed(function() {
            var task = self.selectedTask();
            if (! task) {
                return false;
            }
            return ! task.isCreated();
        });

        self.showCancel = ko.computed(function() {
            var task = self.selectedTask();
            if (! task) {
                return false;
            }
            return task.isCreated() && task.isChanged();
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
                } else if (self.selectedTask().seconds() > 0) {
                    return "{{ trans('texts.resume') }}";
                } else {
                    return "{{ trans('texts.start') }}";
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
                return task.matchesFilter(self.filter(), self.filterState(), self.filterStatusId());
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
        }

        self.removeTask = function(task) {
            self.tasks.remove(task);
        }

        self.selectTask = function(task) {
            if (model.isChanged()) {
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
        self.created_at = ko.observable(moment.utc().format('YYYY-MM-DD HH:mm:ss'));
        self.task_status_id = ko.observable();
        self.custom_value1 = ko.observable('');
        self.custom_value2 = ko.observable('');

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
                if (project.public_id() != -1 && projectMap[project.public_id()]) {
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
            if (model.sendingRequest()) {
                return false;
            }
            if (! self.checkForOverlaps()) {
                swal("{{ trans('texts.task_errors') }}");
                return;
            }
            if (self.isValid() !== true) {
                toastr.error("{{ trans('texts.error_refresh_page') }}");
                throw self.isValid();
                return;
            }

            var data = 'client_id=' + self.client_id()
                            + '&project_id=' + self.project_id()
                            + '&project_name=' + encodeURIComponent(self.project() ? self.project().name() : '')
                            + '&description=' + encodeURIComponent(self.description())
                            + '&custom_value1=' + encodeURIComponent(self.custom_value1())
                            + '&custom_value2=' + encodeURIComponent(self.custom_value2())
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
            model.sendingRequest(true);
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
                    model.sendingRequest(false);
                }, 1500);
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
            if (! self.isRunning()) {
                self.addTime();
            }
            if (data.task_status) {
                self.task_status_id(data.task_status.public_id);
            }

            // Trigger isChanged to update
            self.client_id.valueHasMutated();
            self.project_id.valueHasMutated();
            self.description.valueHasMutated();
        }

        self.checkForOverlaps = function() {
            var lastTime = 0;
            var isValid = true;
            var running = [];

            for (var i=0; i<self.time_log().length; i++) {
                var timeLog = self.time_log()[i];
                var startValid = true;
                var endValid = true;
                if (!timeLog.isEmpty()) {
                    if ((lastTime && timeLog.startTime() < lastTime) || (timeLog.endTime() && timeLog.startTime() > timeLog.endTime())) {
                        startValid = false;
                    }
                    if (timeLog.endTime() && timeLog.endTime() < Math.min(timeLog.startTime(), lastTime)) {
                        endValid = false;
                    }
                    lastTime = Math.max(lastTime, timeLog.endTime());
                    if (timeLog.isRunning()) {
                        running.push(timeLog);
                    }
                }
                timeLog.isStartValid(startValid);
                timeLog.isEndValid(endValid);
                if (! startValid || ! endValid) {
                    isValid = false;
                }
                if (running.length > 1) {
                    $.each(running, function(i, time) {
                        time.isEndValid(false);
                    });
                    isValid = false;
                }
            }

            return isValid;
        }

        self.checkForEmpty = function() {
            setTimeout(function() {
                var hasEmpty = false;
                var times = self.time_log();
                for (var i=0; i<times.length; i++) {
                    var timeLog = times[i];
                    if (! timeLog.endTime()) {
                        hasEmpty = true;
                    }
                }
                if (! hasEmpty) {
                    self.addTime();
                }
            }, 0);
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

        self.isChanged = ko.computed(function() {
            var data = self.data;
            if (! self.public_id()) {
                return true;
            }
            var oldProjectId = data.project ? data.project.public_id : 0;
            if (oldProjectId != (self.project_id()||0)) {
                return true;
            }
            var oldClientId = data.client ? data.client.public_id : 0;
            if (oldClientId != (self.client_id()||0)) {
                return true;
            }
            if (data.description != self.description()) {
                return true;
            }
            if (data.custom_value1 != self.custom_value1()) {
                return true;
            }
            if (data.custom_value2 != self.custom_value2()) {
                return true;
            }
            var times = data.time_log instanceof Array ? JSON.stringify(data.time_log) : data.time_log;
            if (times != JSON.stringify(self.times())) {
                return true;
            }
            return false;
        });

        self.sortedTimes = ko.computed(function() {
            var times = self.time_log();
            times.sort(function (left, right) {
                if (! left.startTime()) {
                    return 1;
                } else if (! right.startTime()) {
                    return -1;
                }
                return left.startTime() - right.startTime();
            });
            return times;
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

        self.onChange = function() {
            self.checkForOverlaps();
            self.checkForEmpty();
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
                    times.push([timeLog.startTime(), timeLog.endTime()]);
                }
            }
            return times;
        }

        self.matchesFilter = function(filter, filterState, filterStatusId) {
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

            if (filterStatusId) {
                if (self.task_status_id() != filterStatusId) {
                    return false;
                }
            }

            return true;
        }

        self.onStartClick = function() {
            if (model.sendingRequest()) {
                return false;
            }
            if (! self.checkForOverlaps()) {
                swal("{{ trans('texts.task_errors') }}");
                return;
            }
            if (self.isRunning()) {
                var time = self.lastTime();
                time.endTime(moment().unix());
            } else {
                var lastTime = self.lastTime();
                if (lastTime && ! lastTime.startTime()) {
                    var time = lastTime;
                } else {
                    var time = new TimeModel();
                    self.addTime(time);
                }
                time.startTime(moment().unix());
            }
            if (self.public_id()) {
                var selectedTask = model.selectedTask();
                if (model.isChanged() && selectedTask && selectedTask.public_id() == self.public_id()) {
                    model.onSaveClick();
                } else {
                    self.save();
                }
            }
        }

        self.listItemState = ko.computed(function() {
            var str = '';
            if (self == model.selectedTask()) {
                str = 'active';

                if (self.isChanged()) {
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

        self.clientClass = ko.computed(function() {
            return self.client() && self.client().deleted_at() ? 'archived-link' : '';
        });

        self.projectClass = ko.computed(function() {
            return self.project() && self.project().deleted_at() ? 'archived-link' : '';
        });

        self.startClass = ko.computed(function() {
            if (model.sendingRequest()) {
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
            if (! projectMap[project.public_id()]) {
                return;
            }

            self.project(project);
            self.project_id(project.public_id());

            var project = projectMap[project.public_id()];
            var client = project.client;
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
            model.clock(); // bind to the clock
            return moment.utc(self.created_at()).fromNow();
        });

        self.seconds = function(total) {
            //model.clock(); // bind to the clock
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
                    duration += time.duration.running();
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
            self.time_log.remove(time);
        }

        if (data) {
            self.update(data);
        }
    }

    function ProjectModel(data) {
        var self = this;
        self.name = ko.observable('');
        self.public_id = ko.observable(-1);
        self.deleted_at = ko.observable();

        if (data) {
            ko.mapping.fromJS(data, {}, this);
        }
    }

    function ClientModel(data) {
        var self = this;
        self.name = ko.observable('');
        self.public_id = ko.observable(-1);
        self.contacts = ko.observableArray();
        self.deleted_at = ko.observable();

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
                return (contact.first_name() || '') + ' ' + (contact.last_name() || '');
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
            if (! model.selectedTask()) {
                return false;
            }
            if (! self.isHovered()) {
                return false;
            }
            var times = model.selectedTask().time_log();
            var count = 0;
            for (var i=0; i<times.length; i++) {
                var timeLog = times[i];
                if (timeLog.isEmpty()) {
                    count++;
                }
            }
            if (count > 1) {
                return true;
            }

            return ! self.isEmpty() && times.length > 1;
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
                if (value === null) {
                    self.startTime(0);
                } else {
                    if (self.startTime()) {
                        var orig = self.startDateMidnight().unix();
                    } else {
                        var orig = moment().set('hours', 0).set('minutes', 0).set('seconds', 0).unix();
                    }
                    self.startTime(orig + value);
                }
            }
        });

        self.endTimeOfDay = ko.computed({
            read: function () {
                return self.endTime();
            },
            write: function(value) {
                if (value === null) {
                    self.endTime(0);
                } else {
                    self.endTime(self.startDateMidnight().unix() + value);
                }
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
                    if (self.endTime()) {
                        self.endTime(self.endTime() + diff);
                    }
                } else {
                    self.startTime(newVal.unix());
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
                if (! self.startTime() || ! self.endTime()) {
                    return false;
                }
                return self.endTime() - self.startTime();
            },
            write: function(value) {
                self.endTime(self.startTime() + value);
            }
        });

        self.duration.running = ko.computed({
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

    }

</script>
