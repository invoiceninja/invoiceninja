@extends('accounts.nav')

@section('content')
	@parent

	{{ Former::open_for_files('company/import_map')->addClass('col-md-9 col-md-offset-1') }}
	{{ Former::legend('import_clients') }}
	{{ Former::file('file')->label(trans('texts.csv_file')) }}
	{{ Former::actions( Button::lg_info_submit(trans('texts.upload'))->append_with_icon('open') ) }}
	{{ Former::close() }}

  {{ Former::open('company/export')->addClass('col-md-9 col-md-offset-1') }}
  {{ Former::legend('export_clients') }}
  {{ Former::actions( Button::lg_primary_submit(trans('texts.download'))->append_with_icon('download-alt') ) }}
  {{ Former::close() }}

@stop