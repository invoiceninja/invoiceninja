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
            <h4>{!! trans('texts.migration_select_company_label') !!}</h4>
            <form action="{{ url('/migration/companies') }}" method="post" id="auth-form">
                {{ csrf_field() }}
                <input type="hidden" name="account_key" value="{{ auth()->user()->account->account_key }}">

                @foreach($companies as $company)
                <div class="form-check">
                    <input class="form-check-input" id="{{ $company['company_key'] }}" type="checkbox" name="companies[{{ $company['company_key'] }}][id]" value="{{ $company['company_key'] }}">
                    <label class="form-check-label" for="{{ $company['company_key'] }}">
                        {{ $company['name'] }}
                    </label>
                </div>
                <div class="form-group">
                    <label for="companies[{{ $company['company_key'] }}][force]">
                        <input type="checkbox" id="companies[{{ $company['company_key'] }}][force]" name="companies[{{ $company['company_key'] }}][force]">
                        <small>{!! trans('texts.force_migration') !!}</small>
                    </label>
                </div>
                @endforeach
            </form>
        </div>
        <div class="panel-footer text-right">
            <button form="auth-form" class="btn btn-primary">{!! trans('texts.continue') !!}</button>
        </div>
    </div>
@stop