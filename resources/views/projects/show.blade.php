@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>
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

                {!! Former::open('projects/bulk')->addClass('mainForm') !!}
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
            {{ trans('texts.client') }}: {!! $project->client->present()->link !!}<br/>
            @if ($project->due_date)
                {{ trans('texts.due_date') . ': ' . Utils::fromSqlDate($project->due_date) }}<br/>
            @endif
            @if ($project->budgeted_hours)
                {{ trans('texts.budgeted_hours') . ': ' . $project->budgeted_hours }}<br/>
            @endif
            @if (floatval($project->task_rate))
                {{ trans('texts.task_rate') . ': ' . Utils::formatMoney($project->task_rate) }}<br/>
            @endif
        </div>

        <div class="col-md-3">
			<h3>{{ trans('texts.notes') }}</h3>

            {{ $project->private_notes }}

        </div>

        <div class="col-md-6">
			<h3>{{ trans('texts.progress') }}</h3>


        </div>
    </div>
    </div>
    </div>

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
		if (confirm("{!! trans('texts.are_you_sure') !!}")) {
			$('#action').val('delete');
			$('.mainForm').submit();
		}
	}

	</script>

@stop
