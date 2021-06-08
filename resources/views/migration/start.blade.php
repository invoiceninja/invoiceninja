@extends('header')

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_MANAGEMENT])

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.welcome_to_the_new_version') !!}</h3>
        </div>
        <div class="panel-body">
            <h4>In order to start the migration, we need to know where do you want to migrate.</h4><br/>
            <form action="{{ url('migration/type') }}" method="post" id="select-type-form">
                {{ csrf_field() }}
                @if(Utils::isNinjaProd()) 
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="option" id="option1" value="0" checked>
                        <label class="form-check-label" for="option1">
                            Hosted
                        </label>
                        <p>Migrate to version 5 of Invoice Ninja</p>
                    </div> 
                @else
                    <div class="form-check">
                    <input class="form-check-input" type="radio" name="option" id="option2" value="1" checked">
                    <label class="form-check-label" for="option2">
                        Self-hosted
                    </label>
                    <p>By choosing the 'self-hosted', you are the one in charge of servers.</p>
                    </div>
                @endif
            </form>
        </div>
        <div class="panel-footer text-right">
            <button form="select-type-form" class="btn btn-primary">{!! trans('texts.continue') !!}</button>
        </div>
    </div>

@stop
