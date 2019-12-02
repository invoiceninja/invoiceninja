@extends('migration.layouts.master', ['intro_title' => 'Welcome to next version of Invoice Ninja', 'intro_text' => 'Lets chose which option youd like to use in the future.'])
@section('title', 'Starting the migration')

@section('content')
    <div class="panel panel-default">
        <div class="panel-body">
            <form action="/migration/steps/settings" method="post">
                {{ csrf_field() }}

                <div class="row text-center">
                    <div class="col-md-6">
                        <label for="version">
                            <input type="radio" name="settings" value="remove_everything">
                            Clean start
                        </label>
                        <p>We will wipe all data on the V2 and migrate all from V1.</p>
                    </div>
                    <div class="col-md-6">
                        <label for="version">
                            <input type="radio" name="settings" value="keep_settings">
                            Keep settings
                        </label>
                        <p>We will wipe everything except settings and then migrate.</p>
                    </div>
                </div>


                <div class="pull-right">
                    <button type="submit" class="btn btn-primary">Next step</button>
                </div>

            </form>
        </div>
    </div>
@stop