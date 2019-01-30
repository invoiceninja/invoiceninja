@extends('header')

@section('content')
	@parent
    @include('accounts.nav', ['selected' => ACCOUNT_IMPORT_EXPORT])

	{{ Former::open()->addClass('col-md-9 col-md-offset-1') }}
	{{ Former::legend('Export Client Data') }}
	{{ Button::lg_primary_submit('Download') }}
	{{ Former::close() }}

@stop