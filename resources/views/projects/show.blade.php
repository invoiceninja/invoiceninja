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

                {!! Former::open('vendors/bulk')->addClass('mainForm') !!}
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
                    @if ( ! $project->trashed())
                        @can('create', ENTITY_TASK)
                            {!! Button::primary(trans("texts.new_task"))
                                    ->asLinkTo(URL::to("/tasks/create/{$project->client->public_id}/{$project->public_id}"))
                                    ->appendIcon(Icon::create('plus-sign')) !!}
                        @endcan
                    @endif
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


        </div>
        <div class="col-md-3">
			<h3>{{ trans('texts.notes') }}</h3>


        </div>
        <div class="col-md-6">
			<h3>{{ trans('texts.progress') }}</h3>


        </div>
    </div>
    </div>
    </div>


@stop
