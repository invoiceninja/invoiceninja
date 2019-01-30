@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/Chart.min.js') }}" type="text/javascript"></script>
@stop


@section('content')

    <div class="row">
        <div class="col-md-7">
            <ol class="breadcrumb">
              <li>{{ link_to('/projects', trans('texts.projects')) }}</li>
              <li class='active'>{{ $project->name }}</li> {!! $project->present()->statusLabel !!}
            </ol>
        </div>
        <div class="col-md-5">
            <div class="pull-right">

                {!! Former::open('projects/bulk')->autocomplete('off')->addClass('mainForm') !!}
            		<div style="display:none">
            			{!! Former::text('action') !!}
            			{!! Former::text('public_id')->value($project->public_id) !!}
            		</div>

                @if ( ! $project->is_deleted)
                    @can('edit', $project)
                        {!! DropdownButton::normal(trans('texts.edit_project'))
                            ->withAttributes(['class'=>'normalDropDown'])
                            ->withContents([
                              ($project->trashed() ? false : ['label' => trans('texts.archive_project'), 'url' => "javascript:onArchiveClick()"]),
                              ['label' => trans('texts.delete_project'), 'url' => "javascript:onDeleteClick()"],
                            ]
                          )->split() !!}
                    @endcan
                @endif

                @if ($project->trashed())
                    @can('edit', $project)
                        {!! Button::primary(trans('texts.restore_project'))
                                ->appendIcon(Icon::create('cloud-download'))
                                ->withAttributes(['onclick' => 'onRestoreClick()']) !!}
                    @endcan
                @endif

                {!! Former::close() !!}

            </div>
        </div>
    </div>


    <div class="panel panel-default">
    <div class="panel-body">
	<div class="row">
        <div class="col-md-3">
			<h3>{{ trans('texts.details') }}</h3>
            <h4>
                {!! $project->client->present()->link !!}<br/>
            </h4>
            @if ($project->due_date)
                {{ trans('texts.due_date') . ': ' . Utils::fromSqlDate($project->due_date) }}<br/>
            @endif
            @if ($project->budgeted_hours)
                {{ trans('texts.budgeted_hours') . ': ' . $project->budgeted_hours }}<br/>
            @endif
            @if ($project->present()->defaultTaskRate)
                {{ trans('texts.task_rate') . ': ' . $project->present()->defaultTaskRate }}<br/>
            @endif

            @if ($account->customLabel('project1') && $project->custom_value1)
                {{ $account->present()->customLabel('project1') . ': ' }} {!! nl2br(e($project->custom_value1)) !!}<br/>
            @endif
            @if ($account->customLabel('project2') && $project->custom_value2)
                {{ $account->present()->customLabel('project2') . ': ' }} {!! nl2br(e($project->custom_value2)) !!}<br/>
            @endif

        </div>

        <div class="col-md-3">
			<h3>{{ trans('texts.notes') }}</h3>
            {{ $project->private_notes }}
        </div>

        <div class="col-md-4">
            <h3>{{ trans('texts.summary') }}
			<table class="table" style="width:100%">
				<tr>
					<td><small>{{ trans('texts.tasks') }}</small></td>
					<td style="text-align: right">{{ $chartData->count }}</td>
				</tr>
				<tr>
					<td><small>{{ trans('texts.duration') }}</small></td>
					<td style="text-align: right">
                        {{ Utils::formatTime($chartData->duration) }}
                        @if (floatval($project->budgeted_hours))
            				[{{ round($chartData->duration / ($project->budgeted_hours * 60 * 60) * 100) }}%]
                        @endif
                    </td>
				</tr>
			</table>
			</h3>

        </div>

    </div>
    </div>
    </div>

    @if ($chartData->duration)
        <canvas id="chart-canvas" height="50px" style="background-color:white;padding:20px;display:none"></canvas><br/>
    @endif

    <ul class="nav nav-tabs nav-justified">
		{!! Form::tab_link('#tasks', trans('texts.tasks')) !!}
	</ul><br/>

	<div class="tab-content">
        <div class="tab-pane" id="tasks">
            @include('list', [
                'entityType' => ENTITY_TASK,
                'datatable' => new \App\Ninja\Datatables\ProjectTaskDatatable(true, true),
                'projectId' => $project->public_id,
                'clientId' => $project->client->public_id,
                'url' => url('api/tasks/' . $project->client->public_id . '/' . $project->public_id),
            ])
        </div>
    </div>

    <script type="text/javascript">

    var loadedTabs = {};

	$(function() {
		$('.normalDropDown:not(.dropdown-toggle)').click(function(event) {
            openUrlOnClick('{{ URL::to('projects/' . $project->public_id . '/edit') }}', event)
		});
		$('.primaryDropDown:not(.dropdown-toggle)').click(function(event) {
			openUrlOnClick('{{ URL::to('tasks/create/' . $project->client->public_id . '/' . $project->public_id ) }}', event);
		});

        $('.nav-tabs a[href="#tasks"]').tab('show');

        var chartData = {!! json_encode($chartData) !!};
        loadChart(chartData);
	});

	function onArchiveClick() {
		$('#action').val('archive');
		$('.mainForm').submit();
	}

	function onRestoreClick() {
		$('#action').val('restore');
		$('.mainForm').submit();
	}

	function onDeleteClick() {
		if (confirm({!! json_encode(trans('texts.are_you_sure')) !!})) {
			$('#action').val('delete');
			$('.mainForm').submit();
		}
	}

    function loadChart(data) {
        if (! data.duration) {
            return;
        }
        var ctx = document.getElementById('chart-canvas').getContext('2d');
        $('#chart-canvas').fadeIn();
        window.myChart = new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                legend: {
                    display: false,
                },
                scales: {
                    xAxes: [{
                        type: 'time',
                        time: {
                            unit: 'day',
                            round: 'day',
                        },
                        gridLines: {
                            display: false,
                        },
                    }],
                    yAxes: [{
                        ticks: {
                            @if ($project->budgeted_hours)
                                max: {{ max($project->budgeted_hours, $chartData->duration / 60 / 60) }},
                            @endif
                            beginAtZero: true,
                            callback: function(label, index, labels) {
                                return roundToTwo(label) + " {{ trans('texts.hours') }}";
                            }
                        },
                    }]
                }
            }
        });
    }


	</script>

@stop
