@extends('migration.layouts.master', ['intro_title' => 'Welcome to next version of Invoice Ninja', 'intro_text' => 'Lets chose which option youd like to use in the future.'])
@section('title', 'Starting the migration')

@section('content')
    <div class="panel panel-default">
        <div class="panel-body">
            <form action="/migration/steps/option" method="post">
                {{ csrf_field() }}

                <div class="row text-center">
                    <div class="col-md-6">
                        <label for="version">
                            <input type="radio" name="version" value="hosted">
                            Hosted version
                        </label>
                        <p>Switch to official servers, and let us handle all the server managing.</p>
                    </div>
                    <div class="col-md-6">
                        <label for="version">
                            <input type="radio" name="version" value="self_hosted">
                            Self-hosted version
                        </label>
                        <p>Migrate data to your own server. Keep full control of your server.</p>
                    </div>
                </div>


                <div class="pull-right">
                    <button type="submit" class="btn btn-primary">Next step</button>
                </div>

            </form>
        </div>
    </div>
@stop