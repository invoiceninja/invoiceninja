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
            <h4>Awesome! Please select the company you would like to apply migration.</h4>
            <form action="/migration/companies" method="post" id="auth-form">
                {{ csrf_field() }}
                <input type="hidden" name="account_key" value="{{ auth()->user()->account->account_key }}">
                    
                @foreach($companies as $company)
                <div class="form-check">
                    <input class="form-check-input" id="company_{{ $company->id }}" type="checkbox" name="companies[{{ $company->id }}][id]" id="company1" value="{{ $company->id }}" checked>
                    <label class="form-check-label" for="company_{{ $company->id }}">
                        Name: {{ $company->settings->name }} ID: {{ $company->id }}
                    </label>
                </div>
                <div class="form-group">
                    <input type="checkbox" name="companies[{{ $company->id }}][force]">
                    <label for="force">Force migration</label>
                    <small>* All current company data will be wiped.</small>
                </div>
                @endforeach
            </form>
        </div>
        <div class="panel-footer text-right">
            <button onclick="document.getElementById('auth-form').submit();" class="btn btn-primary">{!! trans('texts.continue') !!}</button>
        </div>
    </div>
@stop