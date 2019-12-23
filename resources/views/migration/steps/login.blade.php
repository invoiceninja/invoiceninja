@extends('migration.layouts.master', ['intro_title' => 'Let\'s connect to your account.', 'intro_text' => 'Give us info so we can proceed.'])
@section('title', 'Account')

@section('content')
<div class="panel panel-default">
    <div class="panel-body">
        <div class="col-md-6 col-md-offset-3">
            <form action="/migration/steps/login" method="post">
                {{ csrf_field() }}

                <div class="form-group">
                    <label for="email">E-mail address</label>
                    <input type="email" class="form-control" name="email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" name="password">
                </div>

                @if(session()->get('migration_option') == 'self_hosted')
                    <div class="form-group">
                        <label for="x_api_secret">X-API-SECRET</label>
                        <input type="password" class="form-control" name="x_api_secret">
                    </div>

                    <div class="form-group">
                        <label for="self_hosted_url">Self-hosted url</label>
                        <input type="text" class="form-control" name="self_hosted_url" placeholder="Including: http:// or https://">
                    </div>
                @endif

                <div class="form-group text-center">
                    <button class="btn btn-primary">Next step</button>
                </div>
            </form>
        </div>
    </div>
    <div class="panel-body text-center">
        <a href="/migration/steps/register">I don't have an account</a>
    </div>
</div>
@stop