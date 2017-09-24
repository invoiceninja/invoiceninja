<script type="text/javascript">

    function ViewModel() {
        var self = this;
        self.tasks = ko.observableArray();
        self.filter = ko.observable('');
        self.clock = ko.observable(0);
        self.formChanged = ko.observable(false);

        self.selectedTask = ko.observable(false);
        self.selectedClient = ko.observable(false);
        self.selectedProject = ko.observable(false);

        self.onSaveClick = function() {
            if (! model.selectedTask() || ! model.formChanged()) {
                return;
            }
            var data = $('#taskForm').serialize();
            var task = model.selectedTask();
            data += '&time_log=' + JSON.stringify(task.times());
            var url = '{{ url('/tasks') }}';
            var method = 'post';
            if (task.public_id()) {
                method = 'put';
                url += '/' + task.public_id();
            }
            $.ajax({
                dataType: 'json',
                type: method,
                data: data,
                url: url,
                accepts: {
                    json: 'application/json'
                },
                success: function(response) {
                    console.log(response);
                    var task = self.selectedTask();
                    if (task.isNew()) {
                        //self.addTask(task);
                    } else {
                        //self.removeTask(task.original);
                        //self.addTask(task);
                    }
                    task.update(response);
                    self.formChanged(false);
                    //self.selectTask(task);
                },
            });
        }

        self.submitBulkAction = function(action, task) {
            if (! task || ! action) {
                return false;
            }
            var data = {
                id: task.public_id(),
                action: action,
            }
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
                    }
                },
                error: function(error) {
                    console.log(error);
                }
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
                    $('.search').focus();
                } else {
                    task.update(task.data);
                }
                self.formChanged(false);
            });

            return false;
        }

        self.onFilterFocus = function(data) {
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
            self.filter(task.client().displayName());
            self.selectedClient(task.client());
            return false;
        }

        self.viewProject = function(task) {
            self.filter(task.project().name());
            self.selectedProject(task.project());
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
                self.filter('');
                if (! task.client()) {
                    $('.client-select input.form-control').focus();
                } else if (! task.project()) {
                    $('.project-select input.form-control').focus();
                } else {
                    $('#description').focus();
                }
            }
        }

        self.tock = function(startTime) {
            self.clock(self.clock() + 1);
            setTimeout(function() {
                model.tock();
            }, 1000);
        }

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

        self.filteredTasks = ko.computed(function() {

            // filter the data
            if(! self.filter()) {
                var tasks = self.tasks();
            } else {
                var filtered = ko.utils.arrayFilter(self.tasks(), function(task) {
                    return task.matchesFilter(self.filter());
                });
                var tasks = filtered.length == 0 ? self.tasks() : filtered;
            }

            // sort the data
            tasks.sort(function (left, right) {
                return right.createdAt() - left.createdAt();
                /*
                right = right.firstTime() ? right.firstTime().order() : right.createdAt();
                left = left.firstTime() ? left.firstTime().order() : left.createdAt();
                return right - left;
                */
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
            if (task == self.selectedTask()) {
                task = false;
                $('.search').focus();
            }

            // if a client is selected the project list will be filtered
            // this prevents the new task's project from being show
            // to fix it we're clearing the list and then firing a
            // client change event to re-filter the list
            refreshProjectList(true);

            //var clone = new TaskModel(task.data);
            //clone.original = task;
            //self.selectedTask(clone);

            self.selectedTask(task);
            //self.filter('');

            if (task && ! task.project()) {
                $('select#client_id').trigger('change');
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
        self.actionButtonVisible = ko.observable(false);
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

        self.update = function(data) {
            self.data = data;
            var times = JSON.parse(data.time_log);
            data.time_log = false;
            ko.mapping.fromJS(data, self.mapping, this);
            self.time_log.removeAll();
            for (var i=0; i<times.length; i++) {
                self.time_log.push(new TimeModel(times[i]));
            }
        }

        if (data) {
            self.update(data);
        }

        self.isNew = ko.computed(function() {
            return ! self.public_id();
        });

        self.isCreated = ko.computed(function() {
            return self.public_id();
        });

        self.showActionButton = function() {
            self.actionButtonVisible(true);
        }

        self.hideActionButton = function() {
            self.actionButtonVisible(false);
        }

        self.addTime = function(time) {
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

        self.matchesFilter = function(filter) {
            filter = filter.toLowerCase();
            var parts = filter.split(' ');
            for (var i=0; i<parts.length; i++) {
                var part = parts[i];
                var isMatch = false;
                if (self.description().toLowerCase().indexOf(part) >= 0) {
                    isMatch = true;
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
            return true;
        }

        self.onStartClick = function() {
            if (self.isRunning()) {
                var time = self.lastTime();
                time.endTime(moment().unix());
            } else {
                var time = new TimeModel();
                time.startTime(moment().unix());
                self.addTime(time);
            }
        }

        self.listItemState = ko.computed(function() {
            var str = '';
            if (self == model.selectedTask()) {
                str = 'active';
            }
            if (! self.project()) {
                return str;
            }
            var projectId = self.project().public_id();
            var colorNum = (projectId-1) % 8;
            return str + ' list-group-item-type' + (colorNum+1);

        });

        self.isRunning = ko.computed(function() {
            var timeLog = self.time_log();
            if (! timeLog.length) {
                return false;
            }
            var time = timeLog[timeLog.length-1];
            return time.isRunning();
        });

        self.clientName = ko.computed(function() {
            return self.client() ? self.client().displayName() : '';
        });

        self.projectName = ko.computed(function() {
            return self.project() ? self.project().name() : '';
        });

        self.startClass = ko.computed(function() {
            return self.isRunning() ? 'btn-danger' : 'btn-success';
        });

        self.startIcon = ko.computed(function() {
            return self.isRunning() ? 'glyphicon glyphicon-stop' : 'glyphicon glyphicon-play';
        });

        self.description.truncated = ko.computed(function() {
            return truncate(self.description(), self.actionButtonVisible() ? 35 : 60);
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

        self.duration = ko.computed(function() {
            model.clock(); // bind to the clock
            if (! self.time_log().length) {
                return '0:00:00';
            }
            var time = self.lastTime();
            var now = new Date().getTime();
            var duration = 0;
            if (time.isRunning()) {
                var duration = now - (time.startTime() * 1000);
                duration = Math.floor(duration / 100) / 10;
            } else {
                self.time_log().forEach(function(time){
                    duration += time.duration();
                });
            }

            var duration = moment.duration(duration * 1000);
            return Math.floor(duration.asHours()) + moment.utc(duration.asMilliseconds()).format(":mm:ss");
        });
    }

    function ProjectModel(data) {
        var self = this;
        self.name = ko.observable('');
        self.public_id = ko.observable(0);

        if (data) {
            ko.mapping.fromJS(data, {}, this);
        }
    }

    function ClientModel(data) {
        var self = this;
        self.public_id = ko.observable(0);
        self.name = ko.observable('');
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
        self.duration = ko.observable(0);
        self.actionsVisible = ko.observable(false);
        self.isStartValid = ko.observable(true);
        self.isEndValid = ko.observable(true);

        if (data) {
            self.startTime(data[0]);
            self.endTime(data[1]);
        };

        self.order = ko.computed(function() {
            return self.startTime();
        });

        self.isEmpty = ko.computed(function() {
            return !self.startTime() && !self.endTime();
        });

        self.isRunning = ko.computed(function() {
            return self.startTime() && !self.endTime();
        });

        self.age = ko.computed(function() {
            return moment.unix(self.startTime()).fromNow();
        });

        self.duration = ko.computed(function() {
            return self.endTime() - self.startTime();
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
