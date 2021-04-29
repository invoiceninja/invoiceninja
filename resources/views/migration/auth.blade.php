@extends('header')

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_MANAGEMENT])

    @include('migration.includes.errors')

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.welcome_to_the_new_version') !!}</h3>
        </div>
        <div class="panel-body">
            <h4>{!! trans('texts.migration_create_account_notice') !!}</h4><br/>
            <form action="{{ url('/migration/auth') }}" method="post" id="auth-form">
                {{ csrf_field() }}

                <div class="form-group">
                    <label for="email">{!! trans('texts.email_address') !!} *</label>
                    <input type="email" name="email" class="form form-control">
                </div>

                <div class="form-group">
                    <label for="password">{!! trans('texts.password') !!} *</label>
                    <input type="password" name="password" class="form form-control">
                </div>

                @if(!Utils::isNinjaProd())
                    <div class="form-group">
                        <label for="api_secret">{!! trans('texts.api_secret') !!}</label>
                        <input type="api_secret" name="api_secret" class="form form-control">
                        <small>{!! trans('texts.migration_api_secret_notice') !!}</small>
                    </div>
                @endif
            </form>
        </div>
        <div class="panel-footer text-right">
            <button form="auth-form" class="btn btn-primary">{!! trans('texts.continue') !!}</button>
        </div>
    </div>
@stop
