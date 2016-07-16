@extends('emails.master_user')

@section('body')
    <div>
        {{ trans('texts.job_failed', ['name' => $name]) }}
    </div>
@stop