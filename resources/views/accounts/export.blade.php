@extends('accounts.nav')

@section('content')
	@parent

	{{ Former::open()->addClass('col-md-9 col-md-offset-1') }}
	{{ Former::legend('Export Client Data') }}
	{{ Button::lg_primary_submit('Download') }}
	{{ Former::close() }}

@stop