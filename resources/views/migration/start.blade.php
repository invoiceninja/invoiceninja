@extends('header')

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_MANAGEMENT])

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.welcome_to_the_new_version') !!}</h3>
        </div>
        <div class="panel-body">
            <h4>{!! trans('texts.next_step_data_download') !!}</h4>
        </div>
        <div class="panel-footer text-right">
            <a href="/migration/download" class="btn btn-primary">{!! trans('texts.continue') !!}</a>
        </div>
    </div>

@stop