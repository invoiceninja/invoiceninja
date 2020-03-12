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
            <h4>Let's continue with authentication.</h4><br/>
            <form action="/migration/auth" method="post" id="auth-form">
                {{ csrf_field() }}
                <div class="form-group">
                    <label for="email">E-mail address</label>
                    <input type="email" name="email" class="form form-control">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" class="form form-control">
                </div>
            </form>
        </div>
        <div class="panel-footer text-right">
            <button onclick="document.getElementById('auth-form').submit();" class="btn btn-primary">{!! trans('texts.continue') !!}</button>
        </div>
    </div>
@stop
