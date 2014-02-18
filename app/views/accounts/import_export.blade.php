@extends('accounts.nav')

@section('content')
	@parent

	{{ Former::open_for_files('company/import_map')->addClass('col-md-9 col-md-offset-1') }}
	{{ Former::legend('Import Client Data') }}
	{{ Former::file('file')->label('Select CSV file') }}
	{{ Former::actions( Button::lg_primary_submit('Upload') ) }}
	{{ Former::close() }}

  {{ Former::open('company/export')->addClass('col-md-9 col-md-offset-1') }}
  {{ Former::legend('Export Client Data') }}
  {{ Former::actions( Button::lg_primary_submit('Download') ) }}
  {{ Former::close() }}

@stop