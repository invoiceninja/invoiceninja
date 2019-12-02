@extends('migration.layouts.master', ['intro_title' => 'Clients', 'intro_text' => 'Press the button to migrate the clients.'])
@section('title', 'Starting the migration')

@section('content')
    <div class="panel panel-default">
        <div class="panel-body">
            <form action="/migration/steps/clients" method="post">
                {{ csrf_field() }}

                <div class="pull-right">
                    <button type="submit" class="btn btn-primary">Migrate & continue</button>
                </div>

            </form>
        </div>
    </div>
@stop