@extends('header')

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_MANAGEMENT])

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.welcome_to_the_new_version') !!}</h3>
        </div>
        <div class="panel-body">
            <h4>{!! trans('texts.download_data') !!}</h4>
            <form action="{{ url('/migration/download') }}" method="post">
                {!! csrf_field() !!}
                <button class="btn btn-primary">{!! trans('texts.download') !!}</button>
            </form>
        </div>
        <div class="panel-footer text-right">
            <a href="{{ url('/migration/import') }}" class="btn btn-primary">{!! trans('texts.continue') !!}</a>
        </div>
    </div>
@stop