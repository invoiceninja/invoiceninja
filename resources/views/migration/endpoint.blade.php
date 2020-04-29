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
            <h4>We need to know the link of your application.</h4><br/>
            <form action="/migration/endpoint" method="post" id="input-endpoint-form">
                {{ csrf_field() }}
                <div class="form-check">
                    <div class="form-group">
                        <label for="endpoint">Link</label>
                        <input type="text" class="form-control" name="endpoint" required placeholder="Example: https://myinvoiceninja.com">
                    </div>
                </div>
            </form>
        </div>
        <div class="panel-footer text-right">
            <button onclick="document.getElementById('input-endpoint-form').submit();" class="btn btn-primary">{!! trans('texts.continue') !!}</button>
        </div>
    </div>

@stop
