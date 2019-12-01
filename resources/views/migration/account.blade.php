@extends('migration.layouts.master', ['intro_title' => 'Let\'s connect to your account.', 'intro_text' => 'Give us info so we can proceed.'])
@section('title', 'Account')

@section('content')
    <div class="panel panel-default">
        <div class="panel-body">
            @if(session('version') == 'hosted')
                @include('migration.includes.account.hosted')
            @else
                @include('migration.includes.account.self_hosted')
            @endif
        </div>
        <div class="panel-body text-center">
            <a href="/migration/account/create">I don't have an account</a>
        </div>
    </div>
@stop