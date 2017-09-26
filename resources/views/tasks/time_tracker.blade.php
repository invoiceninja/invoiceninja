@extends('master')

@section('head')
	@parent

    <link href="{{ asset('css/built.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

@stop

@section('head_css')
    @parent

    <style type="text/css">

		@media (max-width: 768px) {
			#formDiv {
				position: relative;
			}
		}

		@media (max-width: 768px) {
			#clock,
			#startLabel {
				display: none;
			}
			.navbar-right button {
				padding-left: 0px;
			}
		}

		button .glyphicon {
			vertical-align: text-top;
		}

        a:focus {
            outline: none;
        }

		.fade-color {
			animation-name: fadeToYellow;
		}

		.list-group-item {
			animation-duration: .5s;
		}

		.list-group-item.active {
			background-color: #f8f8f8 !important;
			color: black !important;
			border-left-color: #f8f8f8 !important;
			border-right-color: #f8f8f8 !important;
			box-shadow: 0 0 0 2px rgba(0,0,0,.1), 0 2px 2px rgba(0,0,0,.2);
    		border-color: #fff !important;
		}

		.list-group-item.changed {
			background-color: #ffffaa !important;
		}

		@keyframes fadeToYellow {
			from {background-color: #f8f8f8}
			to {background-color: #ffffaa}
		}

		.list-group-item.active .list-group-item-text,
		.list-group-item.active:focus .list-group-item-text,
		.list-group-item.active:hover .list-group-item-text {
    		color: black !important;
		}

        span.link {
            cursor:pointer;
            color:#337ab7;
            text-decoration:none;
        }

        span.link:hover {
            text-decoration:underline;
        }

        .no-gutter > [class*='col-'] {
            padding-right:0;
            padding-left:0;
        }

        .list-group-item:before {
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 6px;
            content: "";
        }

        .list-group-item-type1:before { background-color: #1c9f77; }
        .list-group-item-type2:before { background-color: #d95d02; }
        .list-group-item-type3:before { background-color: #716cb1; }
        .list-group-item-type4:before { background-color: #e62a8b; }
        .list-group-item-type5:before { background-color: #5fa213; }
        .list-group-item-type6:before { background-color: #e6aa04; }
        .list-group-item-type7:before { background-color: #a87821; }
        .list-group-item-type8:before { background-color: #676767; }

		body {
			/* Margin bottom by footer height */
			margin-bottom: 60px;
		}

		.footer {
			position: fixed;
			bottom: 0;
			width: 100%;
			height: 60px;
			background-color: #313131;
			color: white;
			border-top-width: 3px;
			border-top-color: #aaa;
			border-top-style: ridge;
		}


    </style>

@stop

@section('body')
    <nav class="navbar navbar-default navbar-fixed-top">
        <div class="container-fluid">
            <div class="navbar-collapse" style="padding-top:12px; padding-bottom:12px;">

                <!-- Navbar Buttons -->
                <ul class="nav navbar-right" style="margin-right:0px; padding-left:12px; float:right;">
                    <span id="clock" data-bind="text: selectedTask().duration, visible: selectedTask" class="hidden-xs"
                        style="font-size:28px; color:white; padding-right:12px; vertical-align:middle; display:none;"></span>
                    <button type='button' data-bind="click: onStartClick, css: startClass" class="btn btn-lg">
                        <span id="startLabel" data-bind="text: startLabel"></span>
                        <span data-bind="css: startIcon"></span>
                    </button>
                </ul>

                <!-- Navbar Filter -->
                <div class="input-group input-group-lg">
                    <span class="input-group-addon" style="width:1%;" data-bind="click: onFilterClick" title="{{ trans('texts.filter_sort') }}"><span class="glyphicon glyphicon-filter"></span></span>
                    <input id="search" type="search" class="form-control search" autocomplete="off" autofocus="autofocus"
                        data-bind="event: { focus: onFilterFocus, input: onFilterChanged, keypress: onFilterKeyPress }, value: filter, valueUpdate: 'afterkeydown', attr: {placeholder: placeholder, style: filterStyle }">
					<span class="input-group-addon" style="width:1%;" data-bind="click: onRefreshClick" title="{{ trans('texts.refresh') }}"><span class="glyphicon glyphicon-repeat"></span></span>
                </div>

            </div>
        </div>
    </nav>

    <div style="height:74px"></div>

	<!--
	<div data-bind="text: ko.toJSON(model.selectedTask().client_id)"></div>
	<div data-bind="text: ko.toJSON(model.selectedTask().project_id)"></div>
	<div data-bind="text: ko.toJSON(model.selectedTask().client)"></div>
	<div data-bind="text: ko.toJSON(model.selectedTask().project)"></div>
	Client: <span data-bind="text: ko.toJSON(model.selectedClient().public_id)"></span>
	Project: <span data-bind="text: ko.toJSON(model.selectedProject().public_id)"></span>
	-->

    <div class="container" style="margin: 0 auto;width: 100%;">
        <div class="row no-gutter">

            <!-- Task Form -->
            <div class="col-sm-7 col-sm-push-5">
                <div id="formDiv" class="panel panel-default affix" data-bind="visible: selectedTask" style="margin:20px; display:none;">
                    <div class="panel-body">
						<form id="taskForm">
							<span data-bind="event: { keypress: onFormKeyPress, change: onFormChange, input: onFormChange }">
								<div style="padding-bottom: 20px" class="client-select">
		                            {!! Former::select('client_id')
											->addOption('', '')
											->label('client')
											->data_bind("dropdown: selectedTask().client_id") !!}
								</div>
								<div style="padding-bottom: 20px" class="project-select">
		                            {!! Former::select('project_id')
		                                    ->addOption('', '')
		                                    ->data_bind("dropdown: selectedTask().project_id")
		                                    ->label(trans('texts.project')) !!}
								</div>
	                            {!! Former::textarea('description')
	                                    ->data_bind("value: selectedTask().description")
	                                    ->rows(4) !!}
							</span>

							<center id="buttons" style="padding-top: 30px">
								<span data-bind="visible: showArchive">
									{!! DropdownButton::normal(trans('texts.archive'))
										->withAttributes([
											'class' => 'archive-dropdown',
										])
										->large()
										->withContents([
										  ['label' => trans('texts.delete_task'), 'url' => 'javascript:model.onDeleteClick()'],
										]
									  )->split() !!}
								</span>
								{!! Button::normal(trans('texts.cancel'))
									->appendIcon(Icon::create('remove-circle'))
									->withAttributes([
										'data-bind' => 'click: onCancelClick, visible: showCancel',
									])
									->large() !!}
								&nbsp;
								{!! Button::success(trans('texts.save'))
										->large()
										->appendIcon(Icon::create('floppy-disk'))
										->withAttributes([
											'data-bind' => 'click: onSaveClick, css: { disabled: ! formChanged() || ! isSaveEnabled() }',
										]) !!}
							</center>
						</form>
                    </div>
                </div>
            </div>

            <!-- Task List -->
            <div id="taskList" class="list-group col-sm-5 col-sm-pull-7" data-bind="foreach: filteredTasks" style="display:none">
                <a href="#" data-bind="click: $parent.selectTask, event: { mouseover: showActionButton, mouseout: hideActionButton }, css: listItemState"
                    class="list-group-item">
                    <div class="pull-right" style="text-align:right;">
                        <div data-bind="visible: actionButtonVisible()"
                            data-bindx="style : { visibility : actionButtonVisible() ? '' : 'hidden' }">
                            &nbsp;&nbsp;
                            <button type="button" data-bind="css: startClass, click: onStartClick, clickBubble: false"
                                class="btn btn-sm" style="padding-left:0px; padding-right: 12px; padding-bottom: 6px; margin-top:5px;">
                                <span data-bind="css: startIcon"></span>
                            </button>
                        </div>
                    </div>
                    <div class="pull-right" style="text-align:right; padding-left: 16px;">
                        <div data-bind="text: totalDuration, style: { fontWeight: isRunning() ? 'bold' : '' }"></div>
                        <div data-bind="text: age, style: { fontWeight: isRunning() ? 'bold' : '' }" style="padding-top: 2px"></div>
                    </div>
					<div style="white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
	                    <h4 class="list-group-item-heading">
							<span data-bind="text: description, style: { fontWeight: isRunning() ? 'bold' : '' }"></span>&nbsp;
						</h4>
	                    <p class="list-group-item-text">
	                        <span class="link" data-bind="text: clientName, click: $parent.viewClient, clickBubble: false"></span>
	                        <span data-bind="visible: clientName &amp;&amp; projectName"> | </span>
	                        <span class="link" data-bind="text: projectName, click: $parent.viewProject, clickBubble: false"></span>
							&nbsp;
	                    </p>
					</div>
                </a>
            </div>

        </div>
    </div>

	<!--
	<footer class="footer">
		<div style="padding-left: 16px; padding-top: 16px;">
			<div data-bind="text: statistics"></div>
		</div>
	</footer>
	-->

	@include('tasks.time_tracker_knockout')

    <script type="text/javascript">

        var tasks = {!! $tasks !!};
		var clients = {!! $clients !!};
	    var projects = {!! $projects !!};
        var dateTimeFormat = '{{ $account->getMomentDateTimeFormat() }}';
        var timezone = '{{ $account->getTimezone() }}';

		var clientMap = {};
		var projectMap = {};
		var projectsForClientMap = {};

		function refreshClientList() {
			var $clientSelect = $('select#client_id');
			$clientSelect.find('option').remove().end().combobox('refresh');
			$clientSelect.append(new Option('', ''));

			@if (Auth::user()->can('create', ENTITY_CLIENT))
				//$clientSelect.append(new Option("{{ trans('texts.create_client')}}: $name", '-1'));
			@endif

			for (var i=0; i<clients.length; i++) {
				var client = clients[i];
				var clientName = getClientDisplayName(client);
				if (!clientName) {
					continue;
				}
				$clientSelect.append(new Option(clientName, client.public_id));
			}
			$('select#client_id').combobox('refresh');
		}

		function refreshProjectList(forceClear) {
			var clientId = $('input[name=client_id]').val();
			$projectCombobox = $('select#project_id');
			$projectCombobox.find('option').remove().end().combobox('refresh');
			$projectCombobox.append(new Option('', ''));

			@if (Auth::user()->can('create', ENTITY_PROJECT))
				if (clientId) {
					$projectCombobox.append(new Option("{{ trans('texts.create_project')}}: $name", '-1'));
				}
			@endif

			if (clientId && ! forceClear) {
				var list = projectsForClientMap.hasOwnProperty(clientId) ? projectsForClientMap[clientId] : [];
			} else {
				var list = projects;
			}

			for (var i=0; i<list.length; i++) {
				var project = list[i];
				$projectCombobox.append(new Option(project.name,  project.public_id));
			}

			$('select#project_id').combobox('refresh');
		}

		function addClientToMaps(client) {
			clientMap[client.public_id] = client;
		}

		function addProjectToMaps(project) {
			var client = project.client;
			projectMap[project.public_id] = project;
			if (!projectsForClientMap.hasOwnProperty(client.public_id)) {
				projectsForClientMap[client.public_id] = [];
			}
			projectsForClientMap[client.public_id].push(project);
		}

		function sendKeepAlive() {
			setTimeout(function() {
	            $.get('{{ URL::to('/keep_alive') }}', function (response) {
					if (response == '{{ RESULT_SUCCESS }}') {
						sendKeepAlive()
					} else {
						location.reload();
					}
				}).fail(function() {
					location.reload();
				});
	        }, 1000 * 60 * 15);
		}

        $(function() {

			// setup clients and project comboboxes
			for (var i=0; i<projects.length; i++) {
				addProjectToMaps(projects[i])
			}

			for (var i=0; i<clients.length; i++) {
				addClientToMaps(clients[i]);
			}

			var $clientSelect = $('select#client_id');
			$clientSelect.on('change', function(e) {
				var clientId = $('input[name=client_id]').val();
				var projectId = $('input[name=project_id]').val();
				var client = clientMap[clientId];
				var project = projectMap[projectId];
				if (!clientId && (window.model && !model.selectedTask().client())) {
					e.preventDefault();return;
				}
				if (window.model && model.selectedTask()) {
					var clientModel = new ClientModel(client);
					if (clientId == -1) {
						clientModel.name($('#client_name').val());
					}
					model.selectedTask().client(clientModel);
					model.selectedTask().client_id(clientId);
					model.selectedTask().project_id(0);
					model.selectedTask().project(false);
				}
				refreshProjectList();
			});

			var $projectSelect = $('select#project_id').on('change', function(e) {
				$clientCombobox = $('select#client_id');
				var projectId = $('input[name=project_id]').val();
				if (projectId == '-1') {
					$('input[name=project_name]').val(projectName);
					//var project = new ProjectModel();
					//model.selectedTask().project = project;
					//model.selectedTask().project_id(projectId);
				} else if (projectId) {
					var project = projectMap[projectId];
					model.selectedTask().project(new ProjectModel(project));
					model.selectedTask().project_id(projectId);
					// when selecting a project make sure the client is loaded
					if (project && project.client) {
						var client = clientMap[project.client.public_id];
						if (client) {
							project.client = client;
							model.selectedTask().client(new ClientModel(client));
							model.selectedTask().client_id(client.public_id);
						}
					}
				} else {
					$clientSelect.trigger('change');
				}
			});

			$projectSelect.on('change', function(e) {
				var projectId = $('input[name=project_id]').val();
				if (window.model && model.selectedTask() && projectId == -1) {
					var project = new ProjectModel();
					project.name($('#project_name').val());
					model.selectedTask().project_id(-1);
					model.selectedTask().project(project);
				}
				refreshProjectList();
			});


			Mousetrap.bind('/', function(e) {
	            event.preventDefault();
	            $('#search').focus();
	        });

			@include('partials/entity_combobox', ['entityType' => ENTITY_PROJECT])

			refreshClientList();
			$clientSelect.trigger('change');

			window.model = new ViewModel();
			for (var i=0; i<tasks.length; i++) {
				var task = tasks[i];
				var taskModel = new TaskModel(task);
				model.addTask(taskModel);
			}

			ko.applyBindings(model);
			model.refreshTitle();
			model.tock();

			if (isStorageSupported()) {
				var taskId = localStorage.getItem('last:time_tracker_task');
				var task = model.taskById(taskId);
				if (task) {
					setTimeout(function() {
						model.selectTask(task);
					}, 1);
				}
			}

			$('#taskList').show();

			$('.archive-dropdown:not(.dropdown-toggle)').click(function() {
				model.onArchiveClick();
			});

			toastr.options.timeOut = 3000;
			toastr.options.positionClass = 'toast-bottom-right';

			if (navigator.userAgent != 'Time Tracker') {
				var link = '{{ config('ninja.time_tracker') }}';
				var message = "{{ trans('texts.download_desktop_app') }}";
				if (isMobile) {
					toastr.warning("{{ trans('texts.time_tracker_mobile_help')}}", false, {
						timeOut: 10000,
						closeButton: true,
					});
					if (isIPhone) {
						link = '{{ NINJA_IOS_APP_URL }}';
						message = "{{ trans('texts.download_iphone_app') }}";
					} else if (isAndroid) {
						link = '{{ NINJA_ANDROID_APP_URL }}';
						message = "{{ trans('texts.download_android_app') }}";
					}
				}
				var options = {
					timeOut: 10000,
					closeButton: true,
					onclick: function() {
						window.open(link, '_blank');
					}
				};
				toastr.info(message, false, options);
			}

			sendKeepAlive();

			function setButtonSize() {
				if ($(window).width() > 350) {
					$('#buttons .btn').addClass('btn-lg');
			    } else {
			        $('#buttons .btn').removeClass('btn-lg');
			    }
			}
			$(window).on('resize', function() {
				setButtonSize();
			});
			setButtonSize();

			$(window).on('beforeunload', function () {
				if (navigator.userAgent == 'Time Tracker') {
					return undefined;
				}
				if (model.selectedTask() && model.formChanged()) {
					return "{{ trans('texts.save_or_discard') }}";
				} else {
					return undefined;
				}
		    });

        });

    </script>

@stop
