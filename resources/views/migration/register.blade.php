@extends('migration.layouts.master', ['intro_title' => 'Let\'s create your account', 'intro_text' => 'Let\'s fill few fields and get going.'])
@section('title', 'Create your account')

@section('content')
    <div class="panel panel-default">
        <div class="panel-body">
            @if(session('version') == 'hosted')
                @include('migration.includes.account.register.hosted')
            @else
                @include('migration.includes.account.register.self_hosted')
            @endif
        </div>
        <div class="panel-body text-center">
            <a href="/migration/account">I already have an account</a>
        </div>
    </div>
@stop