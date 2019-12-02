@extends('migration.layouts.master', ['intro_title' => 'Company', 'intro_text' => 'Please select company you\'d like to migrate.'])
@section('title', 'Starting the migration')

@section('content')
    <div class="panel panel-default">
        <div class="panel-body">
            <form action="/migration/company" method="post">
                {{ csrf_field() }}

                <div class="row text-center">

                    @foreach($companies as $index => $company)
                        <div class="col-md-6">
                            <label for="version">
                                <input type="radio" name="company" value="{{ $company->id }}">
                                {{ $index + 1 }} - {{ $company->settings->name }} (ID: {{ $company->id }})
                            </label>
                        </div>
                    @endforeach

                </div>


                <div class="pull-right">
                    <button type="submit" class="btn btn-primary">Next step</button>
                </div>

            </form>
        </div>
    </div>
@stop