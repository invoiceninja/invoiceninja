@extends('accounts.nav')

@section('content')
	@parent

	{{ Former::open_for_files('account/import_map')->addClass('col-md-9 col-md-offset-1') }}
	{{ Former::legend('Import Clients') }}
	{{ Former::file('file')->label('Select CSV file') }}
	{{ Former::actions( Button::lg_primary_submit('Upload') ) }}
	{{ Former::close() }}

@stop