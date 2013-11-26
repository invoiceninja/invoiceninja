@extends('header')

@section('content')
	
	<ul class="nav nav-tabs nav nav-justified">
		{{ HTML::nav_link('account/details', 'Details') }}
		{{ HTML::nav_link('account/settings', 'Settings') }}
		{{ HTML::nav_link('account/import', 'Import', 'account/import_map') }}
		{{ HTML::nav_link('account/export', 'Export') }}
	</ul>
	<p>&nbsp;</p>

@stop