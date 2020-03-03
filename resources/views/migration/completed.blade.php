@extends('header')

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_MANAGEMENT])

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.welcome_to_the_new_version') !!}</h3>
        </div>
        <div class="panel-body">
            Migration has started. We'll update you with status, on your company e-mail.
            <!-- Note: This message needs edit, like next instructions, etc. -->
        </div>
    </div>

@stop